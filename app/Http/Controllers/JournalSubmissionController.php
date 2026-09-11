<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Journal;
use App\Models\JournalSubmission;
use App\Models\PublicPage;
use App\Services\AuditTrail;
use App\Services\PublicSiteProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

final class JournalSubmissionController extends Controller
{
    public function create(string $locale, PublicSiteProfile $profile): View
    {
        return view('journal.submissions.create', $this->shared($locale, $profile));
    }

    public function store(Request $request, string $locale): RedirectResponse
    {
        $validated = $request->validate([
            'website' => ['prohibited'],
            'type' => ['required', Rule::in(['peer_reviewed_research'])],
            'primary_locale' => ['required', Rule::in(['ar', 'en', 'fr'])],
            'title' => ['required', 'string', 'max:500'],
            'abstract' => ['required', 'string', 'max:12000'],
            'keywords' => ['required', 'string', 'max:1200'],
            'manuscript' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:25600'],
            'author_name' => ['required', 'string', 'max:255'],
            'author_email' => ['required', 'email:rfc', 'max:254'],
            'affiliation' => ['nullable', 'string', 'max:255'],
            'orcid' => ['nullable', 'regex:/^0000-000[0-9]-[0-9]{4}-[0-9]{3}[0-9X]$/'],
            'country_code' => ['nullable', 'alpha:ascii', 'size:2'],
            'conflicts' => ['required', 'string', 'max:3000'],
            'funding' => ['required', 'string', 'max:3000'],
            'ethics' => ['required', 'string', 'max:3000'],
            'authorship_confirmed' => ['accepted'],
            'originality_confirmed' => ['accepted'],
            'privacy_confirmed' => ['accepted'],
        ]);

        $journal = Journal::query()->where('status', 'active')->firstOrFail();
        $uuid = (string) Str::uuid();
        $token = Str::random(64);
        $file = $request->file('manuscript');
        $fileHash = hash_file('sha256', $file->getRealPath());
        $extension = match ($file->getMimeType()) {
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            default => Str::lower($file->getClientOriginalExtension()),
        };
        $path = $file->storeAs('journal/submissions/'.$uuid, $fileHash.'.'.$extension, 'local');
        if ($path === false) {
            throw new RuntimeException('The manuscript could not be stored.');
        }

        try {
            $submission = DB::transaction(function () use ($request, $validated, $journal, $uuid, $token, $path, $fileHash, $file): JournalSubmission {
                $submission = JournalSubmission::query()->create([
                    'record_uuid' => $uuid,
                    'journal_id' => $journal->id,
                    'submission_code' => 'MCIJ-SUB-'.now()->year.'-'.Str::upper(Str::substr(str_replace('-', '', $uuid), 0, 8)),
                    'status' => 'submitted',
                    'type' => $validated['type'],
                    'primary_locale' => $validated['primary_locale'],
                    'title' => trim($validated['title']),
                    'abstract' => trim($validated['abstract']),
                    'keywords' => collect(explode(',', $validated['keywords']))->map(fn (string $keyword): string => trim($keyword))->filter()->unique()->values()->all(),
                    'manuscript_path' => $path,
                    'original_filename' => Str::limit((string) preg_replace('/[^\pL\pN._ -]+/u', '-', basename($file->getClientOriginalName())), 255, ''),
                    'file_sha256' => $fileHash,
                    'author_name' => trim($validated['author_name']),
                    'author_email' => Str::lower(trim($validated['author_email'])),
                    'author_email_hash' => $this->secureHash(Str::lower(trim($validated['author_email']))),
                    'affiliation' => trim((string) ($validated['affiliation'] ?? '')) ?: null,
                    'orcid' => trim((string) ($validated['orcid'] ?? '')) ?: null,
                    'country_code' => Str::upper(trim((string) ($validated['country_code'] ?? ''))) ?: null,
                    'declarations' => [
                        'conflicts' => trim($validated['conflicts']),
                        'funding' => trim($validated['funding']),
                        'ethics' => trim($validated['ethics']),
                        'authorship_confirmed' => true,
                        'originality_confirmed' => true,
                        'privacy_confirmed' => true,
                    ],
                    'tracking_token_hash' => $this->secureHash($token),
                    'consent_at' => now()->utc()->startOfSecond(),
                    'received_at' => now()->utc()->startOfSecond(),
                ]);

                AuditTrail::record('journal.submission.received', $submission, [], [
                    'submission_code' => $submission->submission_code,
                    'type' => $submission->type,
                    'file_sha256' => $submission->file_sha256,
                ], ['source' => 'public_submission']);

                return $submission;
            }, 5);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return redirect()->route('journal.public.submissions.confirmation', ['locale' => $locale])
            ->with('journal_submission_receipt', ['code' => $submission->submission_code, 'token' => $token]);
    }

    public function confirmation(string $locale, PublicSiteProfile $profile): View
    {
        $receipt = session('journal_submission_receipt');
        abort_unless(is_array($receipt) && isset($receipt['code'], $receipt['token']), 404);

        return view('journal.submissions.confirmation', $this->shared($locale, $profile) + compact('receipt'));
    }

    public function tracking(string $locale, PublicSiteProfile $profile): View
    {
        return view('journal.submissions.tracking', $this->shared($locale, $profile));
    }

    public function track(Request $request, string $locale, PublicSiteProfile $profile): View
    {
        $validated = $request->validate([
            'submission_code' => ['required', 'string', 'max:80'],
            'tracking_token' => ['required', 'string', 'size:64'],
        ]);

        $submission = JournalSubmission::query()
            ->with(['convertedArticle.decisions'])
            ->where('submission_code', Str::upper(trim($validated['submission_code'])))
            ->first();

        if ($submission === null || ! hash_equals($submission->tracking_token_hash, $this->secureHash($validated['tracking_token']))) {
            return view('journal.submissions.tracking', $this->shared($locale, $profile) + ['trackingError' => true]);
        }

        return view('journal.submissions.tracking', $this->shared($locale, $profile) + compact('submission'));
    }

    /** @return array<string, mixed> */
    private function shared(string $locale, PublicSiteProfile $profile): array
    {
        return [
            'journal' => Journal::query()->where('status', 'active')->firstOrFail(),
            'siteProfile' => $profile->get(),
            'navigation' => PublicPage::query()->published()->where('show_in_navigation', true)->orderBy('navigation_order')->get(),
            'localizedUrls' => collect(['ar', 'en', 'fr'])->mapWithKeys(fn (string $language): array => [$language => preg_replace('#^/'.$locale.'/#', '/'.$language.'/', request()->getRequestUri())])->all(),
        ];
    }

    private function secureHash(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }
}
