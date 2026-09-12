<?php

declare(strict_types=1);

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Models\ContentArticle;
use App\Models\ContentSection;
use App\Services\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class ContentArticleController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:160'], 'status' => ['nullable', Rule::in(['draft', 'published', 'archived'])], 'section' => ['nullable', 'integer', 'exists:content_sections,id']]);
        $articles = ContentArticle::query()->with('section')
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['section'] ?? null, fn ($q, $v) => $q->where('content_section_id', $v))
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where(fn ($match) => $match->where('slug', 'like', '%'.$v.'%')->orWhere('title', 'like', '%'.$v.'%')))
            ->orderByDesc('published_at')->orderByDesc('id')->paginate(20)->withQueryString();
        return view('control.content_articles.index', ['articles' => $articles, 'filters' => $filters, 'sections' => ContentSection::query()->orderBy('sort_order')->get()]);
    }
    public function create(): View { return $this->form(new ContentArticle(['author_name' => 'Ahmad Maadarani', 'publisher_name' => 'Ahmad Maadarani', 'status' => 'draft'])); }
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());
        if ($validated['status'] === 'published' && ! $request->user()->canDo('public-content.publish')) abort(403);
        $article = DB::transaction(function () use ($request, $validated): ContentArticle {
            $attributes = $this->attributes($validated, $request);
            $article = ContentArticle::query()->create($attributes + ['record_uuid' => (string) Str::uuid(), 'slug' => $this->uniqueSlug($validated['slug'] ?: $validated['title']['en']), 'revision' => 1, 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
            AuditTrail::record('content.article.created', $article, [], $article->only(['slug', 'status', 'content_section_id']));
            return $article;
        }, 5);
        return redirect()->route('public-content.articles.edit', ['locale' => app()->getLocale(), 'article' => $article])->with('success', __('public_site.articles.saved'));
    }
    public function edit(string $locale, ContentArticle $article): View { return $this->form($article); }
    public function update(Request $request, string $locale, ContentArticle $article): RedirectResponse
    {
        $validated = $request->validate($this->rules($article));
        if (($validated['status'] !== $article->status || $validated['status'] === 'published') && ! $request->user()->canDo('public-content.publish')) abort(403);
        DB::transaction(function () use ($request, $article, $validated): void {
            $locked = ContentArticle::query()->lockForUpdate()->findOrFail($article->id);
            if ($locked->revision !== (int) $validated['revision']) throw ValidationException::withMessages(['revision' => __('public_site.control.conflict')]);
            $before = $locked->only(['content_section_id', 'slug', 'title', 'status', 'revision', 'cover_image_path']);
            $attributes = $this->attributes($validated, $request, $locked);
            $locked->update($attributes + ['slug' => $this->uniqueSlug($validated['slug'] ?: $validated['title']['en'], $locked->id), 'revision' => $locked->revision + 1, 'updated_by' => $request->user()->id]);
            AuditTrail::record('content.article.updated', $locked, $before, $locked->fresh()->only(array_keys($before)));
        }, 5);
        return back()->with('success', __('public_site.articles.saved'));
    }
    private function form(ContentArticle $article): View { return view('control.content_articles.form', ['article' => $article, 'sections' => ContentSection::query()->active()->orderBy('sort_order')->get()]); }
    private function rules(?ContentArticle $article = null): array
    {
        $rules = ['revision' => [$article ? 'required' : 'nullable', 'integer', 'min:1'], 'content_section_id' => ['required', Rule::exists('content_sections', 'id')->where('status', 'active')], 'slug' => ['nullable', 'string', 'max:180'],
            'author_name' => ['required', 'string', 'max:255'], 'publisher_name' => ['required', 'string', 'max:255'], 'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'source_url' => ['nullable', 'url:http,https', 'max:1000'], 'original_published_at' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])], 'is_featured' => ['nullable', 'boolean'], 'published_at' => ['nullable', 'date']];
        foreach (['ar', 'en', 'fr'] as $locale) {
            $rules["title.{$locale}"] = ['required', 'string', 'max:300']; $rules["excerpt.{$locale}"] = ['required', 'string', 'max:600'];
            $rules["body.{$locale}"] = ['required', 'string', 'max:120000']; $rules["seo_title.{$locale}"] = ['nullable', 'string', 'max:180'];
            $rules["seo_description.{$locale}"] = ['nullable', 'string', 'max:320']; $rules["image_alt.{$locale}"] = ['required_with:cover_image', 'nullable', 'string', 'max:220'];
            $rules["author_biography.{$locale}"] = ['nullable', 'string', 'max:3000']; $rules["tags.{$locale}"] = ['nullable', 'string', 'max:1200'];
        }
        return $rules;
    }
    private function attributes(array $validated, Request $request, ?ContentArticle $article = null): array
    {
        foreach (['title', 'excerpt', 'body', 'seo_title', 'seo_description', 'image_alt', 'author_biography'] as $field) foreach (['ar', 'en', 'fr'] as $locale) $localized[$field][$locale] = trim((string) ($validated[$field][$locale] ?? ''));
        foreach (['ar', 'en', 'fr'] as $locale) $localized['tags'][$locale] = collect(explode(',', (string) ($validated['tags'][$locale] ?? '')))->map(fn ($v) => trim($v))->filter()->unique()->values()->all();
        foreach (['ar', 'en', 'fr'] as $locale) { $localized['seo_title'][$locale] = $localized['seo_title'][$locale] ?: Str::limit($localized['title'][$locale], 180, ''); $localized['seo_description'][$locale] = $localized['seo_description'][$locale] ?: Str::limit($localized['excerpt'][$locale], 320, ''); }
        $cover = $article?->cover_image_path;
        if ($request->hasFile('cover_image')) { $newCover = $request->file('cover_image')->store('editorial/articles', 'public'); if ($cover) Storage::disk('public')->delete($cover); $cover = $newCover; }
        $minutes = max(1, (int) ceil(str_word_count(strip_tags($localized['body']['en'])) / 220));
        $status = $validated['status'];
        return $localized + ['content_section_id' => $validated['content_section_id'], 'author_name' => trim($validated['author_name']), 'publisher_name' => trim($validated['publisher_name']),
            'cover_image_path' => $cover, 'source_url' => $validated['source_url'] ?? null,
            'original_published_at' => $validated['original_published_at'] ?? null,
            'status' => $status, 'is_featured' => $request->boolean('is_featured'), 'reading_minutes' => $minutes,
            'published_at' => $status === 'published' ? ($validated['published_at'] ?? $article?->published_at ?? now()) : null];
    }
    private function uniqueSlug(string $value, ?int $ignore = null): string
    {
        $base = Str::limit(Str::slug($value) ?: 'article', 170, ''); $slug = $base; $i = 2;
        while (ContentArticle::query()->when($ignore, fn ($q) => $q->where('id', '!=', $ignore))->where('slug', $slug)->exists()) $slug = $base.'-'.$i++;
        return $slug;
    }
}
