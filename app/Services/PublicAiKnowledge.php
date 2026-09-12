<?php

namespace App\Services;

use App\Models\Journal;
use App\Models\JournalArticle;
use App\Models\JournalEditorialMember;
use App\Models\JournalIssue;
use App\Models\JournalSection;
use App\Models\PublicPage;
use App\Models\ContentArticle;
use App\Models\ContentSection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Schema;

final class PublicAiKnowledge
{
    public function __construct(private readonly PublicSiteProfile $profile) {}

    /** @return array{context: string, sources: list<array{title: string, url: string}>} */
    public function forQuestion(
        string $locale,
        string $question,
        ?string $currentPath = null,
        bool $canPreviewJournal = false,
    ): array
    {
        $terms = $this->terms($question);
        $journal = $this->publicJournal($canPreviewJournal);
        $pages = $this->pageCandidates($locale, $terms);
        $candidates = $pages
            ->concat($this->contentSectionCandidates($locale, $terms, $currentPath))
            ->concat($this->publicArticleCandidates($locale, $terms, $currentPath))
            ->concat($this->journalOverviewCandidates($journal, $locale, $terms, $currentPath))
            ->concat($this->journalSectionCandidates($journal, $locale, $terms))
            ->concat($this->journalIssueCandidates($journal, $locale, $terms, $currentPath))
            ->concat($this->journalEditorialCandidates($journal, $locale, $terms, $currentPath))
            ->concat($this->journalStaticPageCandidates($journal, $locale, $terms, $currentPath))
            ->concat($this->articleCandidates($journal, $locale, $terms, $currentPath));

        $selected = $candidates->sortByDesc('score')->filter(
            static fn (array $candidate): bool => $candidate['score'] > 0,
        )->take(5);

        if ($selected->isEmpty()) {
            $selected = $pages->filter(
                static fn (array $candidate): bool => in_array($candidate['record']->slug, ['home', 'about', 'entities', 'programmes'], true),
            )->take(4);
        }

        return [
            'context' => $this->context($selected),
            'sources' => $selected->values()->map(static fn (array $candidate): array => [
                'title' => $candidate['title'],
                'url' => $candidate['url'],
            ])->all(),
        ];
    }

    /** @param list<string> $terms */
    private function pageCandidates(string $locale, array $terms): Collection
    {
        if (! Schema::hasTable('public_pages')) {
            return collect();
        }

        return PublicPage::query()->published()->get()->map(function (PublicPage $page) use ($locale, $terms): array {
            $text = implode("\n", [
                $page->localized('title', $locale),
                $page->localized('summary', $locale),
                $page->localized('body', $locale),
            ]);

            return [
                'kind' => 'page',
                'record' => $page,
                'title' => $page->localized('title', $locale),
                'url' => $this->pageUrl($page, $locale),
                'text' => $text,
                'score' => $this->score($text, $terms),
            ];
        });
    }

    private function publicJournal(bool $canPreviewJournal): ?Journal
    {
        if (! Schema::hasTable('journals')) {
            return null;
        }

        $journal = Journal::query()->where('status', 'active')->first();

        return $journal && ($journal->isPubliclyLaunched() || $canPreviewJournal) ? $journal : null;
    }

    /** @param list<string> $terms */
    private function contentSectionCandidates(string $locale, array $terms, ?string $currentPath): Collection
    {
        if (! Schema::hasTable('content_sections')) {
            return collect();
        }

        $currentSlug = null;
        if (is_string($currentPath) && preg_match('~^/(?:ar|en|fr)/articles/sections/([^/]+)$~', $currentPath, $matches) === 1) {
            $currentSlug = $matches[1];
        }

        return ContentSection::query()->active()->orderBy('sort_order')->get()->map(
            function (ContentSection $section) use ($locale, $terms, $currentSlug): array {
                $title = $section->localized('name', $locale);
                $description = $section->localized('description', $locale);
                $text = "Editorial section: {$title}\nDescription: {$description}";
                $score = $this->score($title, $terms) * 6 + $this->score($description, $terms) * 2;
                if ($section->slug === $currentSlug) {
                    $score += 1000000;
                }

                return [
                    'kind' => 'content_section',
                    'record' => $section,
                    'title' => $title,
                    'url' => route('public.articles.sections.show', ['locale' => $locale, 'section' => $section]),
                    'text' => $text,
                    'score' => $score,
                ];
            },
        );
    }

    /** @param list<string> $terms */
    private function journalOverviewCandidates(?Journal $journal, string $locale, array $terms, ?string $currentPath): Collection
    {
        if (! $journal) {
            return collect();
        }

        $frequency = (string) $journal->setting('publication_frequency', 'unconfigured');
        $feePolicy = (string) $journal->setting('fee_policy', 'unconfigured');
        $title = $journal->localized('name', $locale);
        $text = implode("\n", array_filter([
            'Journal code: '.$journal->code,
            'Journal: '.$title,
            'Description: '.$journal->localized('description', $locale),
            'Publisher: '.$journal->publisher_name,
            $journal->issn ? 'ISSN: '.$journal->issn : null,
            $journal->eissn ? 'eISSN: '.$journal->eissn : null,
            'Publication frequency: '.Lang::get('journal.frequencies.'.$frequency, [], $locale),
            'Fees and APC: '.Lang::get('journal.fee_policies.'.$feePolicy, [], $locale),
            'Editorial contact: '.(string) $journal->setting('contact_email', 'info@iuoamc.uk'),
        ]));
        $score = $this->score($text, $terms);
        if ($currentPath === '/'.$locale.'/journal') {
            $score += 1000000;
        }

        return collect([[
            'kind' => 'journal_overview',
            'record' => $journal,
            'title' => $title,
            'url' => route('journal.public.index', ['locale' => $locale]),
            'text' => $text,
            'score' => $score,
        ]]);
    }

    /** @param list<string> $terms */
    private function journalSectionCandidates(?Journal $journal, string $locale, array $terms): Collection
    {
        if (! $journal || ! Schema::hasTable('journal_sections')) {
            return collect();
        }

        return JournalSection::query()->where('journal_id', $journal->id)->active()->orderBy('sort_order')->get()->map(
            function (JournalSection $section) use ($locale, $terms): array {
                $title = $section->localized('name', $locale);
                $description = $section->localized('description', $locale);
                $scope = Lang::get('journal.section_scopes.'.$section->scope, [], $locale);
                $text = "Journal section: {$title}\nScope: {$scope}\nDescription: {$description}";

                return [
                    'kind' => 'journal_section',
                    'record' => $section,
                    'title' => $title,
                    'url' => route('journal.public.index', ['locale' => $locale]).'?section='.rawurlencode($section->slug),
                    'text' => $text,
                    'score' => $this->score($title, $terms) * 6 + $this->score($text, $terms),
                ];
            },
        );
    }

    /** @param list<string> $terms */
    private function journalIssueCandidates(?Journal $journal, string $locale, array $terms, ?string $currentPath): Collection
    {
        if (! $journal || ! Schema::hasTable('journal_issues')) {
            return collect();
        }

        $currentSlug = null;
        if (is_string($currentPath) && preg_match('~^/(?:ar|en|fr)/journal/issues/([^/]+)$~', $currentPath, $matches) === 1) {
            $currentSlug = $matches[1];
        }

        return JournalIssue::query()
            ->where('journal_id', $journal->id)
            ->where('status', 'published')
            ->withCount(['articles' => fn ($query) => $query->published()])
            ->latest('published_at')
            ->limit(40)
            ->get()
            ->map(function (JournalIssue $issue) use ($locale, $terms, $currentSlug): array {
                $title = $issue->localized('title', $locale);
                $text = implode("\n", [
                    'Journal issue: '.$title,
                    'Volume: '.$issue->volume,
                    'Issue: '.$issue->number,
                    'Description: '.$issue->localized('description', $locale),
                    'Published: '.(string) $issue->published_at?->format('Y-m-d'),
                    'Published articles: '.$issue->articles_count,
                ]);
                $score = $this->score($title, $terms) * 6 + $this->score($text, $terms);
                if ($issue->slug === $currentSlug) {
                    $score += 1000000;
                }

                return [
                    'kind' => 'journal_issue',
                    'record' => $issue,
                    'title' => $title,
                    'url' => route('journal.public.issues.show', ['locale' => $locale, 'issue' => $issue]),
                    'text' => $text,
                    'score' => $score,
                ];
            });
    }

    /** @param list<string> $terms */
    private function journalEditorialCandidates(?Journal $journal, string $locale, array $terms, ?string $currentPath): Collection
    {
        if (! $journal || ! Schema::hasTable('journal_editorial_members')) {
            return collect();
        }

        $members = JournalEditorialMember::query()
            ->where('journal_id', $journal->id)
            ->where('status', 'active')
            ->whereNotNull('consented_at')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        $memberText = $members->map(function (JournalEditorialMember $member) use ($locale): string {
            return implode(' — ', array_filter([
                $member->name,
                (string) Lang::get('journal.board_roles.'.$member->role, [], $locale),
                $member->localized('title', $locale),
                $member->localized('affiliation', $locale),
                $member->localized('biography', $locale),
                $member->orcid ? 'ORCID '.$member->orcid : null,
            ]));
        })->implode("\n");
        $title = (string) Lang::get('journal.editorial_governance', [], $locale);
        $text = implode("\n", array_filter([
            $title,
            (string) Lang::get('journal.editorial_governance_intro', [], $locale),
            (string) Lang::get('journal.governance_independence', [], $locale),
            (string) Lang::get('journal.governance_independence_text', [], $locale),
            $this->textValues(Lang::get('journal.editorial_roles', [], $locale)),
            $memberText,
        ]));
        $score = $this->score($text, $terms);
        if ($currentPath === '/'.$locale.'/journal/editorial-governance') {
            $score += 1000000;
        }

        return collect([[
            'kind' => 'journal_editorial_governance',
            'record' => $journal,
            'title' => $title,
            'url' => route('journal.public.editorial-governance', ['locale' => $locale]),
            'text' => $text,
            'score' => $score,
        ]]);
    }

    /** @param list<string> $terms */
    private function journalStaticPageCandidates(?Journal $journal, string $locale, array $terms, ?string $currentPath): Collection
    {
        if (! $journal) {
            return collect();
        }

        $frequency = (string) $journal->setting('publication_frequency', 'unconfigured');
        $feePolicy = (string) $journal->setting('fee_policy', 'unconfigured');
        $definitions = [
            [
                'kind' => 'journal_policies',
                'title' => Lang::get('journal.policies', [], $locale),
                'url' => route('journal.public.policies', ['locale' => $locale]),
                'path' => '/'.$locale.'/journal/policies',
                'text' => implode("\n", [
                    (string) Lang::get('journal.policies_intro', [], $locale),
                    $this->textValues(Lang::get('journal.policy', [], $locale)),
                    (string) Lang::get('journal.publication_information_text', [
                        'frequency' => Lang::get('journal.frequencies.'.$frequency, [], $locale),
                        'fees' => Lang::get('journal.fee_policies.'.$feePolicy, [], $locale),
                        'email' => $journal->setting('contact_email', 'info@iuoamc.uk'),
                    ], $locale),
                ]),
            ],
            [
                'kind' => 'journal_author_guidelines',
                'title' => Lang::get('journal.author_guidelines', [], $locale),
                'url' => route('journal.public.author-guidelines', ['locale' => $locale]),
                'path' => '/'.$locale.'/journal/author-guidelines',
                'text' => implode("\n", [
                    (string) Lang::get('journal.author_guidelines_intro', [], $locale),
                    (string) Lang::get('journal.before_submission_text', [], $locale),
                    $this->textValues(Lang::get('journal.guidelines', [], $locale)),
                ]),
            ],
            [
                'kind' => 'journal_submission',
                'title' => Lang::get('journal.submit_manuscript', [], $locale),
                'url' => route('journal.public.submissions.create', ['locale' => $locale]),
                'path' => '/'.$locale.'/journal/submit',
                'text' => implode("\n", [
                    (string) Lang::get('journal.submission_intro', [], $locale),
                    (string) Lang::get('journal.submission_security_text', [], $locale),
                    (string) Lang::get('journal.submission_check_file', [], $locale),
                    (string) Lang::get('journal.submission_check_metadata', [], $locale),
                    (string) Lang::get('journal.submission_check_declarations', [], $locale),
                    (string) Lang::get('journal.tracking_intro', [], $locale),
                ]),
            ],
        ];

        return collect($definitions)->map(function (array $definition) use ($terms, $currentPath, $journal): array {
            $score = $this->score((string) $definition['title'], $terms) * 6
                + $this->score($definition['text'], $terms);
            if ($currentPath === $definition['path']) {
                $score += 1000000;
            }

            return $definition + ['record' => $journal, 'score' => $score];
        });
    }

    /** @param list<string> $terms */
    private function articleCandidates(
        ?Journal $journal,
        string $locale,
        array $terms,
        ?string $currentPath,
    ): Collection {
        if (! $journal || ! Schema::hasTable('journal_articles') || ! Schema::hasTable('journal_article_translations')) {
            return collect();
        }

        $currentSlug = null;
        if (is_string($currentPath) && preg_match('~^/(?:ar|en|fr)/journal/articles/([^/]+)$~', $currentPath, $matches) === 1) {
            $currentSlug = $matches[1];
        }

        $query = JournalArticle::query()
            ->where('journal_id', $journal->id)
            ->published()
            ->where('status', 'published')
            ->with(['translations', 'authors']);

        if ($currentSlug === null && $terms !== []) {
            $query->whereHas('translations', function ($translationQuery) use ($terms): void {
                $translationQuery->where(function ($matchQuery) use ($terms): void {
                    foreach ($terms as $term) {
                        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
                        foreach (['title', 'subtitle', 'abstract', 'body', 'keywords'] as $column) {
                            $matchQuery->orWhere($column, 'like', '%'.$escaped.'%');
                        }
                    }
                });
            });
        } elseif ($currentSlug !== null) {
            $query->where(function ($articleQuery) use ($currentSlug, $terms): void {
                $articleQuery->where('slug', $currentSlug);
                if ($terms !== []) {
                    $articleQuery->orWhereHas('translations', function ($translationQuery) use ($terms): void {
                        $translationQuery->where(function ($matchQuery) use ($terms): void {
                            foreach ($terms as $term) {
                                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
                                foreach (['title', 'subtitle', 'abstract', 'body', 'keywords'] as $column) {
                                    $matchQuery->orWhere($column, 'like', '%'.$escaped.'%');
                                }
                            }
                        });
                    });
                }
            });
        }

        return $query->latest('published_at')->limit(40)->get()->map(function (JournalArticle $article) use ($locale, $terms, $currentSlug): array {
            $translation = $article->translation($locale);
            $authors = $article->authors->pluck('name')->filter()->implode(', ');
            $keywords = implode(', ', $translation?->keywords ?? []);
            $body = $this->relevantExcerpt((string) $translation?->body, $terms, 3500);
            $text = implode("\n", array_filter([
                'Publication type: '.$article->type,
                'Article code: '.$article->article_code,
                $article->doi ? 'DOI: '.$article->doi : null,
                $authors !== '' ? 'Authors: '.$authors : null,
                'Title: '.(string) $translation?->title,
                $translation?->subtitle ? 'Subtitle: '.$translation->subtitle : null,
                'Abstract: '.(string) $translation?->abstract,
                $keywords !== '' ? 'Keywords: '.$keywords : null,
                'Article text: '.$body,
            ]));
            $score = $this->score((string) $translation?->title, $terms) * 8
                + $this->score((string) $translation?->abstract, $terms) * 3
                + $this->score($keywords, $terms) * 5
                + $this->score((string) $translation?->body, $terms);

            if ($article->slug === $currentSlug) {
                $score += 1000000;
            }

            return [
                'kind' => 'article',
                'record' => $article,
                'title' => (string) ($translation?->title ?: $article->article_code),
                'url' => route('journal.public.articles.show', ['locale' => $locale, 'article' => $article->slug]),
                'text' => $text,
                'score' => $score,
            ];
        });
    }

    /** @return list<string> */
    private function terms(string $question): array
    {
        $parts = preg_split('/[^\p{L}\p{N}-]+/u', mb_strtolower($question)) ?: [];

        return array_values(array_unique(array_filter(
            $parts,
            static fn (string $term): bool => mb_strlen($term) >= 2,
        )));
    }

    /** @param list<string> $terms */
    private function publicArticleCandidates(string $locale, array $terms, ?string $currentPath): Collection
    {
        if (! Schema::hasTable('content_articles')) return collect();
        $currentSlug = null;
        if (is_string($currentPath) && preg_match('~^/(?:ar|en|fr)/articles/([^/]+)$~', $currentPath, $matches) === 1) $currentSlug = $matches[1];
        $query = ContentArticle::query()->published()->with('section');
        if ($currentSlug) $query->where('slug', $currentSlug);
        elseif ($terms !== []) $query->where(function ($match) use ($terms): void {
            foreach ($terms as $term) { $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term); foreach (['title', 'excerpt', 'body', 'tags'] as $column) $match->orWhere($column, 'like', '%'.$escaped.'%'); }
        });
        return $query->latest('published_at')->limit(40)->get()->map(function (ContentArticle $article) use ($locale, $terms, $currentSlug): array {
            $tags = implode(', ', ($article->tags ?? [])[$locale] ?? []);
            $text = implode("\n", array_filter(['Content type: general editorial article', 'Section: '.$article->section?->localized('name', $locale), 'Author: '.$article->author_name, 'Publisher: '.$article->publisher_name, 'Title: '.$article->localized('title', $locale), 'Excerpt: '.$article->localized('excerpt', $locale), 'Tags: '.$tags, 'Article text: '.$this->relevantExcerpt($article->localized('body', $locale), $terms, 3500)]));
            $score = $this->score($article->localized('title', $locale), $terms) * 8 + $this->score($article->localized('excerpt', $locale), $terms) * 3 + $this->score($tags, $terms) * 5 + $this->score($article->localized('body', $locale), $terms);
            if ($article->slug === $currentSlug) $score += 1000000;
            return ['kind' => 'public_article', 'record' => $article, 'title' => $article->localized('title', $locale), 'url' => route('public.articles.show', ['locale' => $locale, 'article' => $article]), 'text' => $text, 'score' => $score];
        });
    }

    private function textValues(mixed $value): string
    {
        if (is_array($value)) {
            return implode("\n", array_filter(array_map(
                fn (mixed $item): string => $this->textValues($item),
                $value,
            )));
        }

        return is_scalar($value) ? trim((string) $value) : '';
    }

    /** @param list<string> $terms */
    private function score(string $text, array $terms): int
    {
        $haystack = mb_strtolower($text);

        return array_sum(array_map(
            static fn (string $term): int => mb_substr_count($haystack, $term),
            $terms,
        ));
    }

    private function relevantExcerpt(string $text, array $terms, int $limit): string
    {
        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        foreach ($terms as $term) {
            $position = mb_stripos($text, $term);
            if ($position !== false) {
                $start = max(0, $position - 700);

                return mb_substr($text, $start, $limit);
            }
        }

        return mb_substr($text, 0, $limit);
    }

    /** @param Collection<int, array{title: string, url: string, text: string, score: int}> $selected */
    private function context(Collection $selected): string
    {
        $sections = $selected->values()->map(function (array $candidate, int $index): string {
            $number = $index + 1;

            return "[SOURCE {$number}]\nTITLE: {$candidate['title']}\nURL: {$candidate['url']}\n".
                mb_substr($candidate['text'], 0, 3500);
        })->all();

        $registry = collect($this->profile->get()['entities'])->map(function (array $entity): string {
            $registrations = collect($entity['registrations'])->map(
                static fn (string $number, string $label): string => "{$label}: {$number}",
            )->implode('; ');

            return trim("{$entity['code']} — {$entity['legal_name']} — {$registrations}", ' —');
        })->implode("\n");

        return implode("\n\n", $sections)."\n\n[OFFICIAL PUBLIC REGISTRY]\n{$registry}";
    }

    private function pageUrl(PublicPage $page, string $locale): string
    {
        if ($page->slug === 'home') {
            return route('public.home', ['locale' => $locale]);
        }

        if ($page->template === 'entity') {
            return route('public.entities.show', [
                'locale' => $locale,
                'entity' => str($page->slug)->after('entity-'),
            ]);
        }

        return route('public.pages.show', ['locale' => $locale, 'public_page' => $page]);
    }
}
