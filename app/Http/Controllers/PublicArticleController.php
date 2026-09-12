<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ContentArticle;
use App\Models\ContentSection;
use App\Models\PublicPage;
use App\Services\PublicSiteProfile;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\Response;

final class PublicArticleController extends Controller
{
    public function index(Request $request, string $locale, PublicSiteProfile $profile, ?ContentSection $section = null): View
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:160']]);
        if ($section) abort_unless($section->status === 'active', 404);
        $articles = ContentArticle::query()->published()->with('section')
            ->when($section, fn ($q) => $q->whereBelongsTo($section))
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where(function ($s) use ($v): void { $s->where('title', 'like', '%'.$v.'%')->orWhere('excerpt', 'like', '%'.$v.'%')->orWhere('tags', 'like', '%'.$v.'%'); }))
            ->orderByDesc('is_featured')->orderByDesc('published_at')->paginate(12)->withQueryString();
        return view('articles.index', $this->shared($locale, $profile) + ['articles' => $articles, 'sections' => ContentSection::query()->active()->orderBy('sort_order')->get(), 'currentSection' => $section, 'filters' => $filters]);
    }
    public function show(string $locale, ContentArticle $article, PublicSiteProfile $profile): View
    {
        abort_unless($article->status === 'published' && $article->published_at?->isPast(), 404);
        $article->increment('views_count'); $article->load('section');
        $related = ContentArticle::query()->published()->where('id', '!=', $article->id)->when($article->content_section_id, fn ($q, $id) => $q->where('content_section_id', $id))->latest('published_at')->limit(3)->get();
        return view('articles.show', $this->shared($locale, $profile) + compact('article', 'related'));
    }
    public function feed(string $locale): Response
    {
        return response()->view('articles.feed', ['articles' => ContentArticle::query()->published()->latest('published_at')->limit(30)->get()], 200, ['Content-Type' => 'application/rss+xml; charset=UTF-8']);
    }
    private function shared(string $locale, PublicSiteProfile $profile): array
    {
        return ['siteProfile' => $profile->get(), 'navigation' => PublicPage::query()->published()->where('show_in_navigation', true)->orderBy('navigation_order')->get(),
            'localizedUrls' => collect(['ar', 'en', 'fr'])->mapWithKeys(fn ($language) => [$language => preg_replace('#^/'.$locale.'/#', '/'.$language.'/', request()->getRequestUri())])->all()];
    }
}
