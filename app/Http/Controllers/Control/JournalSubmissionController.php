<?php

declare(strict_types=1);

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Models\JournalArticle;
use App\Models\JournalAuthor;
use App\Models\JournalSubmission;
use App\Models\JournalSubmissionRevision;
use App\Services\AuditTrail;
use App\Services\JournalNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class JournalSubmissionController extends Controller
{
    public function __construct(private readonly JournalNotificationService $notifications) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:160'],
            'status' => ['nullable', Rule::in(JournalSubmission::STATUSES)],
            'type' => ['nullable', Rule::in(JournalArticle::TYPES)],
        ]);

        $base = JournalSubmission::query()
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['type'] ?? null, fn ($query, string $type) => $query->where('type', $type));

        $submissions = (clone $base)
            ->with('convertedArticle')
            ->when($filters['q'] ?? null, function ($query, string $value): void {
                $query->where(function ($search) use ($value): void {
                    $search->where('submission_code', 'like', '%'.$value.'%')
                        ->orWhere('title', 'like', '%'.$value.'%')
                        ->orWhere('author_name', 'like', '%'.$value.'%');
                });
            })
            ->orderByDesc('received_at')
            ->paginate(20)
            ->withQueryString();

        $counts = JournalSubmission::query()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');

        return view('control.journal.submissions.index', compact('submissions', 'counts', 'filters'));
    }

    public function show(string $locale, JournalSubmission $submission): View
    {
        $submission->load(['convertedArticle', 'handler', 'revisions']);

        return view('control.journal.submissions.show', compact('submission'));
    }

    public function screen(Request $request, string $locale, JournalSubmission $submission): RedirectResponse
    {
        DB::transaction(function () use ($request, $submission): void {
            $locked = JournalSubmission::query()->lockForUpdate()->findOrFail($submission->id);
            abort_unless($locked->status === 'submitted', 409);
            $locked->update(['status' => 'screening', 'handled_by' => $request->user()->id]);
            AuditTrail::record('journal.submission.screening_started', $locked, ['status' => 'submitted'], ['status' => 'screening']);
        }, 5);

        return back()->with('success', trans('journal.messages.submission_screening'));
    }

    public function decline(Request $request, string $locale, JournalSubmission $submission): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:5000']]);

        DB::transaction(function () use ($request, $submission, $validated): void {
            $locked = JournalSubmission::query()->lockForUpdate()->findOrFail($submission->id);
            abort_unless(in_array($locked->status, ['submitted', 'screening'], true), 409);
            $oldStatus = $locked->status;
            $locked->update([
                'status' => 'declined',
                'editorial_note' => trim($validated['reason']),
                'handled_by' => $request->user()->id,
            ]);
            AuditTrail::record('journal.submission.declined', $locked, ['status' => $oldStatus], ['status' => 'declined']);
            $this->notifications->queue($locked->journal, 'submission_declined', $locked->author_email, $locked->primary_locale, [
                'name' => $locked->author_name,
                'code' => $locked->submission_code,
                'title' => $locked->title,
            ], $locked);
        }, 5);

        return redirect()->route('journal.control.submissions.index', ['locale' => $locale])->with('success', trans('journal.messages.submission_declined'));
    }

    public function convert(Request $request, string $locale, JournalSubmission $submission): RedirectResponse
    {
        $article = DB::transaction(function () use ($request, $submission): JournalArticle {
            $locked = JournalSubmission::query()->lockForUpdate()->findOrFail($submission->id);
            abort_unless(in_array($locked->status, ['submitted', 'screening'], true), 409);

            $uuid = (string) Str::uuid();
            $slugBase = Str::slug($locked->title) ?: 'submission';
            $article = JournalArticle::query()->create([
                'record_uuid' => $uuid,
                'journal_id' => $locked->journal_id,
                'article_code' => 'MCIJ-'.now()->year.'-'.Str::upper(Str::substr(str_replace('-', '', $uuid), 0, 8)),
                'slug' => Str::limit($slugBase, 160, '').'-'.Str::lower(Str::substr(str_replace('-', '', $uuid), 0, 8)),
                'type' => $locked->type,
                'status' => 'draft',
                'primary_locale' => $locked->primary_locale,
                'license' => 'all-rights-reserved',
                'received_at' => $locked->received_at->toDateString(),
                'declarations' => $locked->declarations,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
            $article->translations()->create([
                'locale' => $locked->primary_locale,
                'title' => $locked->title,
                'abstract' => $locked->abstract,
                'body' => '',
                'keywords' => $locked->keywords,
                'references' => [],
                'seo_title' => Str::limit($locked->title, 180, ''),
                'seo_description' => Str::limit($locked->abstract, 320, ''),
            ]);
            $author = JournalAuthor::query()->create([
                'record_uuid' => (string) Str::uuid(),
                'name' => $locked->author_name,
                'email' => $locked->author_email,
                'orcid' => $locked->orcid,
                'country_code' => $locked->country_code,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
            $article->authors()->attach($author->id, [
                'position' => 1,
                'is_corresponding' => true,
                'affiliation_name' => $locked->affiliation,
            ]);

            $oldStatus = $locked->status;
            $locked->update([
                'status' => 'converted',
                'converted_article_id' => $article->id,
                'handled_by' => $request->user()->id,
            ]);
            AuditTrail::record('journal.submission.converted', $locked, ['status' => $oldStatus], [
                'status' => 'converted',
                'article_id' => $article->id,
                'article_code' => $article->article_code,
            ]);
            $this->notifications->queue($locked->journal, 'submission_screened', $locked->author_email, $locked->primary_locale, [
                'name' => $locked->author_name,
                'code' => $locked->submission_code,
                'title' => $locked->title,
                'article_code' => $article->article_code,
            ], $locked);

            return $article;
        }, 5);

        return redirect()->route('journal.control.articles.edit', ['locale' => $locale, 'article' => $article])->with('success', trans('journal.messages.submission_converted'));
    }

    public function download(string $locale, JournalSubmission $submission): BinaryFileResponse
    {
        abort_unless(Storage::disk('local')->exists($submission->manuscript_path), 404);

        return response()->download(
            Storage::disk('local')->path($submission->manuscript_path),
            $submission->original_filename,
            ['X-Content-Type-Options' => 'nosniff']
        );
    }

    public function downloadRevision(string $locale, JournalSubmission $submission, JournalSubmissionRevision $revision, string $file): BinaryFileResponse
    {
        abort_unless($revision->journal_submission_id === $submission->id, 404);
        $path = $file === 'response-letter' ? $revision->response_letter_path : $revision->manuscript_path;
        $filename = $file === 'response-letter' ? $revision->response_letter_filename : $revision->original_filename;
        abort_unless(filled($path) && Storage::disk('local')->exists($path), 404);

        return response()->download(
            Storage::disk('local')->path($path),
            $filename,
            ['X-Content-Type-Options' => 'nosniff']
        );
    }
}
