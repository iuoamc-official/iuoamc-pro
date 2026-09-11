<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Journal;
use App\Models\JournalArticle;
use App\Models\JournalIssue;
use App\Models\JournalEditorialMember;
use App\Models\PublicPage;
use App\Services\PublicSiteProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function show(Request $request, string $locale, JournalArticle $article, PublicSiteProfile $profile): View
    {
        abort_unless(in_array($article->status, ['published', 'retracted'], true), 404);
        $reading = $request->validate([
            'page' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'view' => ['nullable', Rule::in(['pages', 'full'])],
        ]);
        $article->load(['translations', 'authors', 'issue', 'correctionOf', 'corrections' => fn ($query) => $query->published()]);
        $translation = $article->translation();
        $bodyPages = $this->paginateBody((string) ($translation?->body ?? ''));
        $readingFull = ($reading['view'] ?? 'pages') === 'full';
        $currentPage = $readingFull ? 1 : (int) ($reading['page'] ?? 1);
        abort_if(! $readingFull && $currentPage > count($bodyPages), 404);
        $bodyPage = $readingFull ? (string) ($translation?->body ?? '') : $bodyPages[$currentPage - 1];

        return view('journal.show', $this->shared($locale, $profile) + compact(
            'article', 'translation', 'bodyPages', 'bodyPage', 'currentPage', 'readingFull'
        ));
    }

    public function downloadPdf(string $locale, JournalArticle $article): StreamedResponse
    {
        abort_unless(in_array($article->status, ['published', 'retracted'], true), 404);
        abort_unless($article->pdf_path && Storage::disk('local')->exists($article->pdf_path), 404);

        $article->increment('pdf_downloads_count');
        $filename = Str::slug($article->translation()?->title ?: $article->article_code).'.pdf';

        return Storage::disk('local')->download($article->pdf_path, $filename, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function registry(string $locale, string $registration, PublicSiteProfile $profile): View
    {
        $article = JournalArticle::query()
            ->whereIn('status', ['published', 'retracted'])
            ->where('wicp_registration_number', $registration)
            ->whereNotNull('wicp_verified_at')
            ->where('wicp_registration_number', 'not like', 'WICP-TEST-%')
            ->with(['translations', 'authors', 'issue'])
            ->firstOrFail();

        return view('journal.registry', $this->shared($locale, $profile) + compact('article'));
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
        $editorialMembers = JournalEditorialMember::query()
            ->where('status', 'active')
            ->whereNotNull('consented_at')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('journal.editorial-governance', $this->shared($locale, $profile) + compact('editorialMembers'));
    }


    /** @return list<string> */
    private function paginateBody(string $body, int $targetCharacters = 6000): array
    {
        $body = trim((string) preg_replace("/\r\n?/", "\n", $body));
        if ($body === '') {
            return [''];
        }

        $paragraphs = preg_split('/\n{2,}/u', $body) ?: [$body];
        $pages = [];
        $page = '';

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            while (mb_strlen($paragraph) > $targetCharacters) {
                if ($page !== '') {
                    $pages[] = $page;
                    $page = '';
                }
                $cut = mb_strrpos(mb_substr($paragraph, 0, $targetCharacters), ' ');
                $cut = $cut === false || $cut < (int) ($targetCharacters * 0.6) ? $targetCharacters : $cut;
                $pages[] = trim(mb_substr($paragraph, 0, $cut));
                $paragraph = trim(mb_substr($paragraph, $cut));
            }

            $candidate = $page === '' ? $paragraph : $page."\n\n".$paragraph;
            if ($page !== '' && mb_strlen($candidate) > $targetCharacters) {
                $pages[] = $page;
                $page = $paragraph;
            } else {
                $page = $candidate;
            }
        }

        if ($page !== '') {
            $pages[] = $page;
        }

        return $pages === [] ? [''] : $pages;
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
