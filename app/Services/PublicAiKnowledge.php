<?php

namespace App\Services;

use App\Models\PublicPage;
use Illuminate\Support\Collection;

final class PublicAiKnowledge
{
    public function __construct(private readonly PublicSiteProfile $profile) {}

    /** @return array{context: string, sources: list<array{title: string, url: string}>} */
    public function forQuestion(string $locale, string $question): array
    {
        $terms = $this->terms($question);
        $pages = PublicPage::query()->published()->get()->map(function (PublicPage $page) use ($locale, $terms): array {
            $text = implode("\n", [
                $page->localized('title', $locale),
                $page->localized('summary', $locale),
                $page->localized('body', $locale),
            ]);

            return ['page' => $page, 'text' => $text, 'score' => $this->score($text, $terms)];
        });

        $selected = $pages->sortByDesc('score')->filter(
            static fn (array $candidate): bool => $candidate['score'] > 0,
        )->take(5);

        if ($selected->isEmpty()) {
            $selected = $pages->filter(
                static fn (array $candidate): bool => in_array($candidate['page']->slug, ['home', 'about', 'entities', 'programmes'], true),
            )->take(4);
        }

        return [
            'context' => $this->context($selected, $locale),
            'sources' => $selected->values()->map(fn (array $candidate): array => [
                'title' => $candidate['page']->localized('title', $locale),
                'url' => $this->url($candidate['page'], $locale),
            ])->all(),
        ];
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
    private function score(string $text, array $terms): int
    {
        $haystack = mb_strtolower($text);

        return array_sum(array_map(
            static fn (string $term): int => mb_substr_count($haystack, $term),
            $terms,
        ));
    }

    /** @param Collection<int, array{page: PublicPage, text: string, score: int}> $selected */
    private function context(Collection $selected, string $locale): string
    {
        $sections = $selected->values()->map(function (array $candidate, int $index) use ($locale): string {
            $number = $index + 1;

            return "[SOURCE {$number}]\nURL: {$this->url($candidate['page'], $locale)}\n".
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

    private function url(PublicPage $page, string $locale): string
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
