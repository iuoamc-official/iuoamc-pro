<?php

declare(strict_types=1);

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Models\ContentSection;
use App\Services\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class ContentSectionController extends Controller
{
    public function index(): View { return view('control.content_sections.index', ['sections' => ContentSection::query()->withCount('articles')->orderBy('sort_order')->get()]); }
    public function create(): View { return view('control.content_sections.form', ['section' => new ContentSection]); }
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());
        $section = ContentSection::query()->create($this->attributes($validated) + ['created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
        AuditTrail::record('content.section.created', $section, [], $section->only(['slug', 'status']));
        return redirect()->route('public-content.sections.edit', ['locale' => app()->getLocale(), 'section' => $section])->with('success', __('public_site.articles.section_saved'));
    }
    public function edit(string $locale, ContentSection $section): View { return view('control.content_sections.form', compact('section')); }
    public function update(Request $request, string $locale, ContentSection $section): RedirectResponse
    {
        $validated = $request->validate($this->rules($section)); $before = $section->toArray();
        $section->update($this->attributes($validated) + ['updated_by' => $request->user()->id]);
        AuditTrail::record('content.section.updated', $section, $before, $section->fresh()->toArray());
        return back()->with('success', __('public_site.articles.section_saved'));
    }
    private function rules(?ContentSection $section = null): array
    {
        $rules = ['slug' => ['required', 'alpha_dash:ascii', 'max:100', Rule::unique('content_sections')->ignore($section?->id)], 'status' => ['required', Rule::in(['active', 'inactive'])], 'sort_order' => ['required', 'integer', 'min:1', 'max:1000']];
        foreach (['ar', 'en', 'fr'] as $locale) { $rules["name.{$locale}"] = ['required', 'string', 'max:180']; $rules["description.{$locale}"] = ['nullable', 'string', 'max:500']; }
        return $rules;
    }
    private function attributes(array $validated): array
    {
        foreach (['name', 'description'] as $field) foreach (['ar', 'en', 'fr'] as $locale) $localized[$field][$locale] = trim((string) ($validated[$field][$locale] ?? ''));
        return $localized + ['slug' => Str::slug($validated['slug']), 'status' => $validated['status'], 'sort_order' => $validated['sort_order']];
    }
}
