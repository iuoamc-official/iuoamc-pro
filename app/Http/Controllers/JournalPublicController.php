<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Journal;
use App\Models\JournalArticle;
use App\Models\JournalIssue;
use App\Models\PublicPage;
use App\Services\PublicSiteProfile;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class JournalPublicController extends Controller
{
    public function index(Request $request, string $locale, PublicSiteProfile $profile): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:160'],
            'type' => ['nullable', Rule::in(JournalArticle::TYPES)],
        ]);

        $articles = JournalArticle::query()
            ->published()
            ->with(['translations', 'authors', 'issue'])
            ->when($filters['type'] ?? null, fn ($query, string $type) => $query->where('type', $type))
            ->when($filters['q'] ?? null, fn ($query, string $value) => $query->whereHas('translations', function ($translationQuery) use ($value, $locale): void {
                $translationQuery->where('locale', $locale)->where(function ($textQuery) use ($value): void {
                    $textQuery->where('title', 'like', '%'.$value.'%')->orWhere('abstract', 'like', '%'.$value.'%');
                });
            }))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        return view('journal.index', $this->shared($locale, $profile) + compact('articles', 'filters'));
    }

    public function show(string $locale, JournalArticle $article, PublicSiteProfile $profile): View
    {
        abort_unless(in_array($article->status, ['published', 'retracted'], true), 404);
        $article->load(['translations', 'authors', 'issue', 'correctionOf', 'corrections' => fn ($query) => $query->published()]);

        return view('journal.show', $this->shared($locale, $profile) + compact('article'));
    }

    public function issues(string $locale, PublicSiteProfile $profile): View
    {
        $issues = JournalIssue::query()->where('status', 'published')->withCount(['articles' => fn ($query) => $query->published()])->orderByDesc('published_at')->paginate(12);

        return view('journal.issues', $this->shared($locale, $profile) + compact('issues'));
    }

    public function issue(string $locale, JournalIssue $issue, PublicSiteProfile $profile): View
    {
        abort_unless($issue->status === 'published', 404);
        $issue->load(['articles' => fn ($query) => $query->published()->with(['translations', 'authors'])->orderBy('page_start')->orderBy('id')]);

        return view('journal.issue', $this->shared($locale, $profile) + compact('issue'));
    }

    public function policies(string $locale, PublicSiteProfile $profile): View
    {
        return view('journal.policies', $this->shared($locale, $profile));
    }

    public function authorGuidelines(string $locale, PublicSiteProfile $profile): View
    {
        return view('journal.author-guidelines', $this->shared($locale, $profile));
    }

    public function editorialGovernance(string $locale, PublicSiteProfile $profile): View
    {
        return view('journal.editorial-governance', $this->shared($locale, $profile));
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
}
