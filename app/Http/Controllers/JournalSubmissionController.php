<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Journal;
use App\Models\JournalSubmission;
use App\Models\PublicPage;
use App\Services\AuditTrail;
use App\Services\JournalNotificationService;
use App\Services\PublicSiteProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
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

    public function store(Request $request, string $locale, JournalNotificationService $notifications): RedirectResponse
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
            $submission = DB::transaction(function () use ($request, $validated, $journal, $uuid, $token, $path, $fileHash, $file, $notifications, $locale): JournalSubmission {
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

                $notifications->queue($journal, 'submission_received', $submission->author_email, $locale, [
                    'name' => $submission->author_name,
                    'code' => $submission->submission_code,
                    'title' => $submission->title,
                    'token' => $token,
                    'tracking_url' => route('journal.public.submissions.tracking', ['locale' => $locale]),
                ], $submission);
                $contactEmail = trim((string) $journal->setting('contact_email'));
                if ($contactEmail !== '') {
                    $notifications->queue($journal, 'new_submission_received', $contactEmail, 'en', [
                        'name' => 'Editorial Office',
                        'code' => $submission->submission_code,
                        'title' => $submission->title,
                        'workspace_url' => route('journal.control.submissions.show', ['locale' => 'en', 'submission' => $submission]),
                    ], $submission);
                }

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
            ->with(['convertedArticle.decisions', 'revisions'])
            ->where('submission_code', Str::upper(trim($validated['submission_code'])))
            ->first();

        if ($submission === null || ! hash_equals($submission->tracking_token_hash, $this->secureHash($validated['tracking_token']))) {
            return view('journal.submissions.tracking', $this->shared($locale, $profile) + ['trackingError' => true]);
        }

        return view('journal.submissions.tracking', $this->shared($locale, $profile) + compact('submission'));
    }

    public function storeRevision(Request $request, string $locale, JournalNotificationService $notifications): RedirectResponse
    {
        $validated = $request->validate([
            'submission_code' => ['required', 'string', 'max:80'],
            'tracking_token' => ['required', 'string', 'size:64'],
            'manuscript' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:25600'],
            'response_letter' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
            'author_note' => ['nullable', 'string', 'max:5000'],
        ]);

        $submission = JournalSubmission::query()
            ->with('convertedArticle')
            ->where('submission_code', Str::upper(trim($validated['submission_code'])))
            ->first();
        abort_unless(
            $submission !== null
            && hash_equals($submission->tracking_token_hash, $this->secureHash($validated['tracking_token']))
            && $submission->status === 'converted'
            && $submission->convertedArticle?->status === 'revision_required',
            404
        );

        $uuid = (string) Str::uuid();
        $manuscript = $request->file('manuscript');
        $responseLetter = $request->file('response_letter');
        $manuscriptHash = hash_file('sha256', $manuscript->getRealPath());
        $manuscriptPath = $manuscript->storeAs(
            'journal/submission-revisions/'.$uuid,
            $manuscriptHash.'.'.$this->safeExtension($manuscript),
            'local'
        );
        if ($manuscriptPath === false) {
            throw new RuntimeException('The revised manuscript could not be stored.');
        }

        $responseLetterHash = $responseLetter ? hash_file('sha256', $responseLetter->getRealPath()) : null;
        $responseLetterPath = $responseLetter?->storeAs(
            'journal/submission-revisions/'.$uuid,
            $responseLetterHash.'.'.$this->safeExtension($responseLetter),
            'local'
        );
        if ($responseLetter !== null && $responseLetterPath === false) {
            Storage::disk('local')->delete($manuscriptPath);
            throw new RuntimeException('The response letter could not be stored.');
        }

        try {
            DB::transaction(function () use ($validated, $submission, $uuid, $manuscript, $manuscriptPath, $manuscriptHash, $responseLetter, $responseLetterPath, $responseLetterHash, $notifications): void {
                $locked = JournalSubmission::query()->with('convertedArticle')->lockForUpdate()->findOrFail($submission->id);
                abort_unless($locked->status === 'converted' && $locked->convertedArticle?->status === 'revision_required', 409);
                $revisionNumber = (int) $locked->revisions()->max('revision_number') + 1;
                $revision = $locked->revisions()->create([
                    'record_uuid' => $uuid,
                    'revision_number' => $revisionNumber,
                    'manuscript_path' => $manuscriptPath,
                    'original_filename' => $this->safeFilename($manuscript),
                    'file_sha256' => $manuscriptHash,
                    'response_letter_path' => $responseLetterPath,
                    'response_letter_filename' => $responseLetter ? $this->safeFilename($responseLetter) : null,
                    'response_letter_sha256' => $responseLetterHash,
                    'author_note' => trim((string) ($validated['author_note'] ?? '')) ?: null,
                    'status' => 'received',
                    'received_at' => now()->utc()->startOfSecond(),
                ]);
                AuditTrail::record('journal.submission.revision_received', $revision, [], [
                    'submission_code' => $locked->submission_code,
                    'revision_number' => $revisionNumber,
                    'file_sha256' => $manuscriptHash,
                ], ['source' => 'public_revision_intake']);

                $contactEmail = trim((string) $locked->journal->setting('contact_email'));
                if ($contactEmail !== '') {
                    $notifications->queue($locked->journal, 'revision_received', $contactEmail, 'en', [
                        'name' => 'Editorial Office',
                        'code' => $locked->submission_code,
                        'title' => $locked->title,
                        'revision' => $revisionNumber,
                        'workspace_url' => route('journal.control.submissions.show', ['locale' => 'en', 'submission' => $locked]),
                    ], $revision);
                }
            }, 5);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete(array_filter([$manuscriptPath, $responseLetterPath]));
            throw $exception;
        }

        return redirect()->route('journal.public.submissions.tracking', ['locale' => $locale])
            ->with('success', trans('journal.messages.revision_received'));
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

    private function safeFilename(UploadedFile $file): string
    {
        return Str::limit((string) preg_replace('/[^\pL\pN._ -]+/u', '-', basename($file->getClientOriginalName())), 255, '');
    }

    private function safeExtension(UploadedFile $file): string
    {
        return match ($file->getMimeType()) {
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            default => Str::lower($file->getClientOriginalExtension()),
        };
    }
}
