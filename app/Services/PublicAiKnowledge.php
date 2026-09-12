<?php

namespace App\Services;

use App\Models\Journal;
use App\Models\JournalArticle;
use App\Models\PublicPage;
use App\Models\ContentArticle;
use Illuminate\Support\Collection;
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
        $pages = $this->pageCandidates($locale, $terms);
        $articles = $this->articleCandidates($locale, $terms, $currentPath, $canPreviewJournal);
        $publicArticles = $this->publicArticleCandidates($locale, $terms, $currentPath);
        $candidates = $pages->concat($articles)->concat($publicArticles);

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

    /** @param list<string> $terms */
    private function articleCandidates(
        string $locale,
        array $terms,
        ?string $currentPath,
        bool $canPreviewJournal,
    ): Collection {
        if (! Schema::hasTable('journals') || ! Schema::hasTable('journal_articles') || ! Schema::hasTable('journal_article_translations')) {
            return collect();
        }

        $journal = Journal::query()->where('status', 'active')->first();
        if (! $journal || (! $journal->isPubliclyLaunched() && ! $canPreviewJournal)) {
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
