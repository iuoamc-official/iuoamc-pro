<?php

declare(strict_types=1);

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Models\Journal;
use App\Models\JournalEditorialMember;
use App\Services\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class JournalEditorialMemberController extends Controller
{
    public function index(): View
    {
        return view('control.journal.editorial-members.index', [
            'members' => JournalEditorialMember::query()->orderBy('sort_order')->orderBy('id')->paginate(30),
        ]);
    }

    public function create(): View
    {
        return view('control.journal.editorial-members.form', ['member' => new JournalEditorialMember]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());
        $journal = Journal::query()->where('status', 'active')->firstOrFail();
        $member = DB::transaction(function () use ($request, $validated, $journal): JournalEditorialMember {
            $member = JournalEditorialMember::query()->create($this->attributes($validated) + [
                'record_uuid' => (string) Str::uuid(),
                'journal_id' => $journal->id,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
            AuditTrail::record('journal.editorial_member.created', $member, [], $member->only([
                'name', 'role', 'status', 'country_code', 'orcid', 'consented_at',
            ]));

            return $member;
        }, 5);

        return redirect()->route('journal.control.editorial-members.edit', [
            'locale' => app()->getLocale(), 'editorial_member' => $member,
        ])->with('success', trans('journal.messages.editorial_member_saved'));
    }

    public function edit(string $locale, JournalEditorialMember $editorialMember): View
    {
        return view('control.journal.editorial-members.form', ['member' => $editorialMember]);
    }

    public function update(Request $request, string $locale, JournalEditorialMember $editorialMember): RedirectResponse
    {
        abort_if($editorialMember->status === 'archived', 409);
        $validated = $request->validate($this->rules());
        DB::transaction(function () use ($request, $editorialMember, $validated): void {
            $locked = JournalEditorialMember::query()->lockForUpdate()->findOrFail($editorialMember->id);
            abort_if($locked->status === 'archived', 409);
            $before = $locked->only(['name', 'role', 'status', 'country_code', 'orcid', 'consented_at']);
            $locked->update($this->attributes($validated, $locked) + ['updated_by' => $request->user()->id]);
            AuditTrail::record('journal.editorial_member.updated', $locked, $before, $locked->only([
                'name', 'role', 'status', 'country_code', 'orcid', 'consented_at',
            ]));
        }, 5);

        return back()->with('success', trans('journal.messages.editorial_member_saved'));
    }

    public function archive(Request $request, string $locale, JournalEditorialMember $editorialMember): RedirectResponse
    {
        abort_if($editorialMember->status === 'archived', 409);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        DB::transaction(function () use ($request, $editorialMember, $validated): void {
            $locked = JournalEditorialMember::query()->lockForUpdate()->findOrFail($editorialMember->id);
            abort_if($locked->status === 'archived', 409);
            $oldStatus = $locked->status;
            $locked->update(['status' => 'archived', 'updated_by' => $request->user()->id]);
            AuditTrail::record('journal.editorial_member.archived', $locked, ['status' => $oldStatus], [
                'status' => 'archived',
            ], ['reason' => trim($validated['reason'])]);
        }, 5);

        return redirect()->route('journal.control.editorial-members.index', ['locale' => $locale])
            ->with('success', trans('journal.messages.editorial_member_archived'));
    }

    /** @return array<string, mixed> */
    private function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', Rule::in(JournalEditorialMember::ROLES)],
            'status' => ['required', Rule::in(['draft', 'active'])],
            'country_code' => ['nullable', 'alpha:ascii', 'size:2'],
            'orcid' => ['nullable', 'regex:/^0000-000[0-9]-[0-9]{4}-[0-9]{3}[0-9X]$/'],
            'sort_order' => ['required', 'integer', 'min:1', 'max:1000'],
            'consent_confirmed' => [Rule::requiredIf(fn (): bool => request()->input('status') === 'active'), 'accepted'],
        ];
        foreach (['ar', 'en', 'fr'] as $locale) {
            $rules['title_'.$locale] = ['nullable', 'string', 'max:255'];
            $rules['affiliation_'.$locale] = ['nullable', 'string', 'max:500'];
            $rules['biography_'.$locale] = ['nullable', 'string', 'max:3000'];
        }

        return $rules;
    }

    /** @param array<string, mixed> $validated
     *  @return array<string, mixed>
     */
    private function attributes(array $validated, ?JournalEditorialMember $member = null): array
    {
        $localized = function (string $field) use ($validated): array {
            return collect(['ar', 'en', 'fr'])->mapWithKeys(fn (string $locale): array => [
                $locale => trim((string) ($validated[$field.'_'.$locale] ?? '')),
            ])->all();
        };

        return [
            'name' => trim($validated['name']),
            'role' => $validated['role'],
            'title' => $localized('title'),
            'affiliation' => $localized('affiliation'),
            'biography' => $localized('biography'),
            'country_code' => Str::upper(trim((string) ($validated['country_code'] ?? ''))) ?: null,
            'orcid' => trim((string) ($validated['orcid'] ?? '')) ?: null,
            'status' => $validated['status'],
            'sort_order' => $validated['sort_order'],
            'consented_at' => $validated['status'] === 'active'
                ? ($member?->consented_at ?? now()->utc()->startOfSecond())
                : $member?->consented_at,
        ];
    }
}
