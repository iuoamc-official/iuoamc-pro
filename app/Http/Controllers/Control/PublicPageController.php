<?php

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Models\PublicPage;
use App\Services\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PublicPageController extends Controller
{
    public function index(): View
    {
        return view('control.public_pages.index', [
            'pages' => PublicPage::query()->orderBy('navigation_order')->get(),
        ]);
    }

    public function edit(PublicPage $publicPage): View
    {
        return view('control.public_pages.edit', compact('publicPage'));
    }

    public function update(Request $request, PublicPage $publicPage): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        if ($validated['status'] !== $publicPage->status && ! $request->user()->canDo('public-content.publish')) {
            abort(403);
        }

        DB::transaction(function () use ($request, $publicPage, $validated): void {
            $locked = PublicPage::query()->lockForUpdate()->findOrFail($publicPage->id);
            if ($locked->revision !== (int) $validated['revision']) {
                throw ValidationException::withMessages([
                    'revision' => trans('public_site.control.conflict'),
                ]);
            }

            $old = $locked->only(['navigation_label', 'eyebrow', 'title', 'summary', 'body', 'seo_title', 'seo_description', 'navigation_order', 'show_in_navigation', 'status', 'revision']);
            $content = $this->localizedContent($validated);
            $nextStatus = $validated['status'];

            $locked->fill($content + [
                'navigation_order' => $validated['navigation_order'],
                'show_in_navigation' => $request->boolean('show_in_navigation'),
                'status' => $nextStatus,
                'revision' => $locked->revision + 1,
                'updated_by' => $request->user()->id,
                'published_at' => $nextStatus === 'published' ? ($locked->published_at ?? now()) : null,
            ])->save();

            AuditTrail::record(
                $nextStatus === 'published' ? 'public_page.published' : 'public_page.drafted',
                $locked,
                $old,
                $locked->fresh()->only(array_keys($old)),
                ['locale_set' => ['ar', 'en', 'fr']]
            );
        });

        return redirect()
            ->route('public-content.pages.edit', ['locale' => $request->route('locale'), 'publicPage' => $publicPage])
            ->with('success', trans('public_site.control.saved'));
    }

    /** @return array<string, mixed> */
    private function rules(): array
    {
        $rules = [
            'revision' => ['required', 'integer', 'min:1'],
            'navigation_order' => ['required', 'integer', 'min:0', 'max:1000'],
            'show_in_navigation' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in(['draft', 'published'])],
        ];

        foreach (['ar', 'en', 'fr'] as $locale) {
            foreach (['navigation_label', 'eyebrow', 'title', 'summary', 'seo_title', 'seo_description'] as $field) {
                $rules["{$field}.{$locale}"] = ['required', 'string', 'max:'.($field === 'summary' || $field === 'seo_description' ? 320 : 180)];
            }
            $rules["body.{$locale}"] = ['required', 'string', 'max:12000'];
        }

        return $rules;
    }

    /** @return array<string, array<string, string>> */
    private function localizedContent(array $validated): array
    {
        $content = [];
        foreach (['navigation_label', 'eyebrow', 'title', 'summary', 'body', 'seo_title', 'seo_description'] as $field) {
            foreach (['ar', 'en', 'fr'] as $locale) {
                $content[$field][$locale] = trim($validated[$field][$locale]);
            }
        }

        return $content;
    }
}
