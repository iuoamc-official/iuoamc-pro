<?php

declare(strict_types=1);

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Models\Journal;
use App\Models\JournalSection;
use App\Services\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class JournalSectionController extends Controller
{
    public function index(): View
    {
        return view('control.journal.sections.index', ['sections' => JournalSection::query()->withCount('articles')->orderBy('sort_order')->get()]);
    }

    public function create(): View { return view('control.journal.sections.form', ['section' => new JournalSection]); }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());
        $section = DB::transaction(function () use ($request, $validated): JournalSection {
            $section = JournalSection::query()->create($this->attributes($validated) + [
                'journal_id' => Journal::query()->where('status', 'active')->value('id'),
                'created_by' => $request->user()->id, 'updated_by' => $request->user()->id,
            ]);
            AuditTrail::record('journal.section.created', $section, [], $section->only(['slug', 'scope', 'status']));
            return $section;
        }, 5);
        return redirect()->route('journal.control.sections.edit', ['locale' => app()->getLocale(), 'section' => $section])->with('success', __('journal.section_saved'));
    }

    public function edit(string $locale, JournalSection $section): View { return view('control.journal.sections.form', compact('section')); }

    public function update(Request $request, string $locale, JournalSection $section): RedirectResponse
    {
        $validated = $request->validate($this->rules($section));
        DB::transaction(function () use ($request, $section, $validated): void {
            $before = $section->only(['slug', 'name', 'description', 'scope', 'status', 'sort_order']);
            $section->update($this->attributes($validated) + ['updated_by' => $request->user()->id]);
            AuditTrail::record('journal.section.updated', $section, $before, $section->fresh()->only(array_keys($before)));
        }, 5);
        return back()->with('success', __('journal.section_saved'));
    }

    private function rules(?JournalSection $section = null): array
    {
        $rules = ['slug' => ['required', 'alpha_dash:ascii', 'max:100', Rule::unique('journal_sections')->ignore($section?->id)],
            'scope' => ['required', Rule::in(JournalSection::SCOPES)], 'status' => ['required', Rule::in(['active', 'inactive'])],
            'sort_order' => ['required', 'integer', 'min:1', 'max:1000']];
        foreach (['ar', 'en', 'fr'] as $locale) {
            $rules["name.{$locale}"] = ['required', 'string', 'max:180'];
            $rules["description.{$locale}"] = ['nullable', 'string', 'max:500'];
        }
        return $rules;
    }

    private function attributes(array $validated): array
    {
        foreach (['name', 'description'] as $field) foreach (['ar', 'en', 'fr'] as $locale) $localized[$field][$locale] = trim((string) ($validated[$field][$locale] ?? ''));
        return $localized + ['slug' => Str::slug($validated['slug']), 'scope' => $validated['scope'], 'status' => $validated['status'], 'sort_order' => $validated['sort_order']];
    }
}
