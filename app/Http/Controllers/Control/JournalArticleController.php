<?php

declare(strict_types=1);

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Models\Journal;
use App\Models\JournalArticle;
use App\Models\JournalAuthor;
use App\Models\JournalIssue;
use App\Models\User;
use App\Services\AuditTrail;
use App\Services\JournalWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class JournalArticleController extends Controller
{
    public function __construct(private readonly JournalWorkflow $workflow) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:160'],
            'status' => ['nullable', Rule::in($this->statuses())],
            'type' => ['nullable', Rule::in(JournalArticle::TYPES)],
        ]);

        $base = JournalArticle::query()
            ->when(! $request->user()->canDo('journal.manage') && $request->user()->hasRole('journal-reviewer'), fn ($query) => $query->whereHas('reviews', fn ($reviewQuery) => $reviewQuery->where('reviewer_id', $request->user()->id)))
            ->when($filters['status'] ?? null, fn ($query, string $value) => $query->where('status', $value))
            ->when($filters['type'] ?? null, fn ($query, string $value) => $query->where('type', $value));

        $articles = (clone $base)
            ->with(['translations', 'issue', 'authors'])
            ->when($filters['q'] ?? null, function ($query, string $value): void {
                $query->where(function ($subQuery) use ($value): void {
                    $subQuery->where('article_code', 'like', '%'.$value.'%')
                        ->orWhere('doi', 'like', '%'.$value.'%')
                        ->orWhereHas('translations', fn ($translationQuery) => $translationQuery->where('title', 'like', '%'.$value.'%'));
                });
            })
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $counts = (clone $base)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return view('control.journal.articles.index', compact('articles', 'counts'));
    }

    public function create(): View
    {
        return $this->form(new JournalArticle(['primary_locale' => 'ar', 'type' => 'peer_reviewed_research', 'license' => 'all-rights-reserved']));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules(false));
        $article = DB::transaction(function () use ($request, $validated): JournalArticle {
            $journal = Journal::query()->where('status', 'active')->firstOrFail();
            $uuid = (string) Str::uuid();
            $article = JournalArticle::query()->create([
                'record_uuid' => $uuid,
                'journal_id' => $journal->id,
                'journal_issue_id' => $validated['journal_issue_id'] ?? null,
                'article_code' => 'MCIJ-'.now()->year.'-'.Str::upper(Str::substr(str_replace('-', '', $uuid), 0, 8)),
                'slug' => $this->uniqueSlug($validated['translations']['en']['title'], $uuid),
                'type' => $validated['type'],
                'status' => 'draft',
                'primary_locale' => $validated['primary_locale'],
                'license' => $validated['license'],
                'received_at' => $validated['received_at'] ?? null,
                'declarations' => $validated['declarations'] ?? [],
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            $this->saveTranslations($article, $validated['translations']);
            $this->savePrimaryAuthor($article, $validated['author'], $request->user()->id);
            AuditTrail::record('journal.article.created', $article, [], ['article_code' => $article->article_code, 'type' => $article->type]);

            return $article;
        }, 5);

        return redirect()->route('journal.control.articles.show', ['locale' => app()->getLocale(), 'article' => $article])->with('success', trans('journal.messages.created'));
    }

    public function show(string $locale, JournalArticle $article): View
    {
        $this->authorizeRecordAccess(request(), $article);
        $reviewScope = fn ($query) => request()->user()->canDo('journal.manage')
            ? $query
            : $query->where('reviewer_id', request()->user()->id);
        $article->load(['translations', 'authors', 'issue', 'correctionOf', 'corrections', 'versions', 'decisions.issuer', 'reviews' => $reviewScope, 'reviews.reviewer']);
        $reviewers = request()->user()->canDo('journal.manage')
            ? User::query()->where('status', 'active')->whereHas('roles', fn ($query) => $query->where('slug', 'journal-reviewer'))->orderBy('name')->get(['id', 'name', 'email'])
            : collect();

        $actions = $article->type === 'professional_article'
            ? ($article->status === 'published' ? ['retract'] : ($article->status === 'retracted' ? [] : ['publish_professional']))
            : (['draft' => ['submit'], 'submitted' => ['screen'], 'initial_screening' => ['send_review', 'request_revision', 'reject'], 'under_review' => ['accept', 'request_revision', 'reject'], 'revision_required' => ['resubmit', 'reject'], 'accepted' => ['copyedit'], 'copyediting' => ['typeset'], 'typesetting' => ['ready'], 'ready_to_publish' => ['publish'], 'published' => ['retract']][$article->status] ?? []);

        return view('control.journal.articles.show', compact('article', 'reviewers', 'actions'));
    }

    public function edit(string $locale, JournalArticle $article): View
    {
        abort_unless(in_array($article->status, ['draft', 'revision_required'], true), 403);

        return $this->form($article->load(['translations', 'authors']));
    }

    public function update(Request $request, string $locale, JournalArticle $article): RedirectResponse
    {
        abort_unless(in_array($article->status, ['draft', 'revision_required'], true), 403);
        $validated = $request->validate($this->rules(true));

        DB::transaction(function () use ($request, $article, $validated): void {
            $locked = JournalArticle::query()->lockForUpdate()->findOrFail($article->id);
            if ($locked->lock_version !== (int) $validated['lock_version']) {
                throw ValidationException::withMessages(['lock_version' => trans('journal.errors.conflict')]);
            }

            $old = $locked->only(['journal_issue_id', 'type', 'primary_locale', 'license', 'received_at', 'declarations', 'lock_version']);
            $locked->fill([
                'journal_issue_id' => $validated['journal_issue_id'] ?? null,
                'type' => $validated['type'],
                'primary_locale' => $validated['primary_locale'],
                'license' => $validated['license'],
                'received_at' => $validated['received_at'] ?? null,
                'declarations' => $validated['declarations'] ?? [],
                'lock_version' => $locked->lock_version + 1,
                'updated_by' => $request->user()->id,
            ])->save();
            $this->saveTranslations($locked, $validated['translations']);
            $this->savePrimaryAuthor($locked, $validated['author'], $request->user()->id);
            AuditTrail::record('journal.article.updated', $locked, $old, $locked->fresh()->only(array_keys($old)));
        }, 5);

        return redirect()->route('journal.control.articles.show', ['locale' => app()->getLocale(), 'article' => $article])->with('success', trans('journal.messages.updated'));
    }


    public function publicationAssets(Request $request, string $locale, JournalArticle $article): RedirectResponse
    {
        abort_if(in_array($article->status, ['published', 'retracted'], true), 409);

        $validated = $request->validate([
            'publication_pdf' => ['required', 'file', 'mimes:pdf', 'max:51200'],
            'wicp_registration_number' => [
                'required', 'string', 'max:120',
                Rule::unique('journal_articles', 'wicp_registration_number')->ignore($article->id),
            ],
            'wicp_registered_at' => ['required', 'date_format:Y-m-d'],
            'wicp_verification_url' => ['nullable', 'url:http,https', 'max:1000'],
            'wicp_verified' => ['accepted'],
        ]);

        $uploaded = $validated['publication_pdf'];
        $pdfHash = hash_file('sha256', $uploaded->getRealPath());
        $pdfSize = (int) $uploaded->getSize();
        $directory = 'journal/publication-pdfs/'.$article->record_uuid;
        $path = $uploaded->storeAs($directory, $pdfHash.'.pdf', 'local');

        if (! is_string($path)) {
            throw ValidationException::withMessages(['publication_pdf' => trans('journal.errors.pdf_store_failed')]);
        }

        $previousPath = $article->pdf_path;

        try {
            DB::transaction(function () use ($request, $article, $validated, $path, $pdfHash, $pdfSize): void {
                $locked = JournalArticle::query()->lockForUpdate()->findOrFail($article->id);
                abort_if(in_array($locked->status, ['published', 'retracted'], true), 409);

                $old = $locked->only([
                    'pdf_path', 'pdf_sha256', 'pdf_size', 'wicp_registration_number',
                    'wicp_registered_at', 'wicp_verification_url', 'wicp_verified_at',
                ]);

                $locked->fill([
                    'pdf_path' => $path,
                    'pdf_sha256' => $pdfHash,
                    'pdf_size' => $pdfSize,
                    'wicp_registration_number' => trim($validated['wicp_registration_number']),
                    'wicp_registered_at' => $validated['wicp_registered_at'],
                    'wicp_verification_url' => $validated['wicp_verification_url'] ?? null,
                    'wicp_verified_at' => now()->utc()->startOfSecond(),
                    'lock_version' => $locked->lock_version + 1,
                    'updated_by' => $request->user()->id,
                ])->save();

                AuditTrail::record('journal.article.publication_assets_verified', $locked, $old, $locked->fresh()->only(array_keys($old)));
            }, 5);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        if ($previousPath && $previousPath !== $path) {
            Storage::disk('local')->delete($previousPath);
        }

        return back()->with('success', trans('journal.messages.publication_assets_verified'));
    }

    public function transition(Request $request, string $locale, JournalArticle $article): RedirectResponse
    {
        $validated = $request->validate([
            'lock_version' => ['required', 'integer', 'min:1'],
            'action' => ['required', Rule::in(['submit', 'screen', 'send_review', 'request_revision', 'resubmit', 'accept', 'reject', 'copyedit', 'typeset', 'ready', 'publish', 'publish_professional', 'retract'])],
            'reason' => [Rule::requiredIf(in_array((string) $request->input('action'), ['request_revision', 'reject', 'retract'], true)), 'nullable', 'string', 'max:3000'],
        ]);

        $this->workflow->transition($request->user(), $article->id, (int) $validated['lock_version'], $validated['action'], $validated['reason'] ?? null);

        return back()->with('success', trans('journal.messages.transitioned'));
    }

    public function correction(Request $request, string $locale, JournalArticle $article): RedirectResponse
    {
        abort_unless($request->user()->canDo('journal.publish'), 403);
        abort_unless(in_array($article->status, ['published', 'retracted'], true), 409);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:3000']]);

        $correction = DB::transaction(function () use ($request, $article, $validated): JournalArticle {
            $source = JournalArticle::query()->with(['translations', 'authors'])->lockForUpdate()->findOrFail($article->id);
            $number = $source->corrections()->count() + 1;
            $copy = $source->replicate([
                'record_uuid', 'article_code', 'slug', 'status', 'doi', 'pdf_path', 'pdf_sha256', 'pdf_size', 'pdf_downloads_count',
                'wicp_registration_number', 'wicp_registered_at', 'wicp_verification_url', 'wicp_verified_at',
                'accepted_at', 'published_at', 'retracted_at', 'version_of_record', 'version_of_record_hash', 'lock_version',
            ]);
            $copy->fill([
                'record_uuid' => (string) Str::uuid(),
                'correction_of_id' => $source->id,
                'article_code' => $source->article_code.'-C'.$number,
                'slug' => $source->slug.'-correction-'.$number,
                'status' => 'draft',
                'doi' => null,
                'pdf_path' => null,
                'pdf_sha256' => null,
                'pdf_size' => null,
                'pdf_downloads_count' => 0,
                'wicp_registration_number' => null,
                'wicp_registered_at' => null,
                'wicp_verification_url' => null,
                'wicp_verified_at' => null,
                'accepted_at' => null,
                'published_at' => null,
                'retracted_at' => null,
                'version_of_record' => 0,
                'version_of_record_hash' => null,
                'lock_version' => 1,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ])->save();

            foreach ($source->translations as $translation) {
                $copy->translations()->create($translation->only(['locale', 'title', 'subtitle', 'abstract', 'body', 'keywords', 'references', 'seo_title', 'seo_description']));
            }
            foreach ($source->authors as $author) {
                $authorCopy = $author->replicate(['record_uuid', 'created_by', 'updated_by']);
                $authorCopy->fill([
                    'record_uuid' => (string) Str::uuid(),
                    'created_by' => $request->user()->id,
                    'updated_by' => $request->user()->id,
                ])->save();
                $copy->authors()->attach($authorCopy->id, [
                    'position' => $author->pivot->position,
                    'is_corresponding' => $author->pivot->is_corresponding,
                    'affiliation_name' => $author->pivot->affiliation_name,
                    'affiliation_ror' => $author->pivot->affiliation_ror,
                    'contribution' => $author->pivot->contribution,
                ]);
            }

            AuditTrail::record('journal.article.correction_created', $copy, [], ['source_article_id' => $source->id, 'reason' => $validated['reason']]);

            return $copy;
        }, 5);

        return redirect()->route('journal.control.articles.edit', ['locale' => app()->getLocale(), 'article' => $correction])->with('success', trans('journal.messages.correction_created'));
    }

    private function form(JournalArticle $article): View
    {
        return view('control.journal.articles.form', [
            'article' => $article,
            'issues' => JournalIssue::query()->orderByDesc('volume')->orderByDesc('number')->get(),
        ]);
    }

    private function authorizeRecordAccess(Request $request, JournalArticle $article): void
    {
        if ($request->user()->canDo('journal.manage') || $request->user()->canDo('journal.publish')) {
            return;
        }

        abort_unless(
            $request->user()->hasRole('journal-reviewer')
                && $article->reviews()->where('reviewer_id', $request->user()->id)->exists(),
            404
        );
    }

    /** @return array<string, mixed> */
    private function rules(bool $editing): array
    {
        $rules = [
            'journal_issue_id' => ['nullable', 'integer', 'exists:journal_issues,id'],
            'type' => ['required', Rule::in(JournalArticle::TYPES)],
            'primary_locale' => ['required', Rule::in(['ar', 'en', 'fr'])],
            'license' => ['required', Rule::in(['all-rights-reserved', 'CC-BY-4.0', 'CC-BY-NC-4.0'])],
            'received_at' => ['nullable', 'date_format:Y-m-d'],
            'author.name' => ['required', 'string', 'max:255'],
            'author.latin_name' => ['nullable', 'string', 'max:255'],
            'author.email' => ['nullable', 'email:rfc', 'max:254'],
            'author.orcid' => ['nullable', 'regex:/^0000-000[0-9]-[0-9]{4}-[0-9]{3}[0-9X]$/'],
            'author.affiliation_name' => ['nullable', 'string', 'max:255'],
            'author.affiliation_ror' => ['nullable', 'url', 'max:255'],
            'declarations.conflicts' => ['nullable', 'string', 'max:3000'],
            'declarations.funding' => ['nullable', 'string', 'max:3000'],
            'declarations.ethics' => ['nullable', 'string', 'max:3000'],
        ];

        if ($editing) {
            $rules['lock_version'] = ['required', 'integer', 'min:1'];
        }

        foreach (['ar', 'en', 'fr'] as $locale) {
            $rules["translations.{$locale}.title"] = ['required', 'string', 'max:500'];
            $rules["translations.{$locale}.subtitle"] = ['nullable', 'string', 'max:500'];
            $rules["translations.{$locale}.abstract"] = ['required', 'string', 'max:12000'];
            $rules["translations.{$locale}.body"] = ['required', 'string', 'max:200000'];
            $rules["translations.{$locale}.keywords"] = ['required', 'string', 'max:1200'];
            $rules["translations.{$locale}.references"] = ['nullable', 'string', 'max:50000'];
        }

        return $rules;
    }

    /** @param array<string, array<string, string|null>> $translations */
    private function saveTranslations(JournalArticle $article, array $translations): void
    {
        foreach (['ar', 'en', 'fr'] as $locale) {
            $data = $translations[$locale];
            $article->translations()->updateOrCreate(['locale' => $locale], [
                'title' => trim($data['title']),
                'subtitle' => trim((string) ($data['subtitle'] ?? '')) ?: null,
                'abstract' => trim($data['abstract']),
                'body' => trim($data['body']),
                'keywords' => collect(explode(',', $data['keywords']))->map(fn (string $keyword): string => trim($keyword))->filter()->unique()->values()->all(),
                'references' => collect(preg_split('/\R/u', (string) ($data['references'] ?? '')) ?: [])->map(fn (string $reference): string => trim($reference))->filter()->values()->all(),
                'seo_title' => Str::limit(trim($data['title']), 180, ''),
                'seo_description' => Str::limit(trim($data['abstract']), 320, ''),
            ]);
        }
    }

    /** @param array<string, string|null> $data */
    private function savePrimaryAuthor(JournalArticle $article, array $data, int $actorId): void
    {
        $author = $article->authors()->first();
        if ($author === null) {
            $author = JournalAuthor::query()->create([
                'record_uuid' => (string) Str::uuid(),
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ] + $this->authorAttributes($data));
        } else {
            $author->fill($this->authorAttributes($data) + ['updated_by' => $actorId])->save();
        }

        $article->authors()->sync([$author->id => [
            'position' => 1,
            'is_corresponding' => true,
            'affiliation_name' => trim((string) ($data['affiliation_name'] ?? '')) ?: null,
            'affiliation_ror' => trim((string) ($data['affiliation_ror'] ?? '')) ?: null,
            'contribution' => null,
        ]]);
    }

    /** @param array<string, string|null> $data
     *  @return array<string, string|null>
     */
    private function authorAttributes(array $data): array
    {
        return [
            'name' => trim($data['name']),
            'latin_name' => trim((string) ($data['latin_name'] ?? '')) ?: null,
            'email' => trim((string) ($data['email'] ?? '')) ?: null,
            'orcid' => trim((string) ($data['orcid'] ?? '')) ?: null,
        ];
    }

    private function uniqueSlug(string $title, string $uuid): string
    {
        $base = Str::slug($title) ?: 'article';

        return Str::limit($base, 160, '').'-'.Str::lower(Str::substr(str_replace('-', '', $uuid), 0, 8));
    }

    /** @return list<string> */
    private function statuses(): array
    {
        return ['draft', 'submitted', 'initial_screening', 'under_review', 'revision_required', 'accepted', 'rejected', 'copyediting', 'typesetting', 'ready_to_publish', 'published', 'retracted'];
    }
}
