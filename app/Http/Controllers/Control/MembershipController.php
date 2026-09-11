<?php
declare(strict_types=1);

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\Organization;
use App\Services\InstitutionalAccess;
use App\Services\MembershipRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class MembershipController extends Controller
{
    private function registry(): MembershipRegistry { return app(MembershipRegistry::class); }

    private function organizations(Request $request)
    {
        return app(InstitutionalAccess::class)->constrainOrganizations(Organization::query(), $request->user())
            ->orderBy('display_name')->get(['id', 'display_name', 'status']);
    }

    private function record(Request $request): Membership
    {
        // Read named route parameters explicitly; locale is another route parameter.
        return $this->registry()->scoped($request->user())->with(['organization', 'integrityAudit', 'periods.audit', 'periods.approver'])
            ->findOrFail((int) $request->route('membership'));
    }

    public function index(Request $request): View
    {
        $organizations = $this->organizations($request);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:120'],
            'organization_id' => ['nullable', 'integer', Rule::in($organizations->pluck('id')->all())],
            'status' => ['nullable', Rule::in(['draft', 'pending', 'active', 'scheduled', 'expired', 'suspended', 'rejected', 'revoked'])]]);
        $base = $this->registry()->scoped($request->user());
        $stats = ['total' => (clone $base)->count(), 'pending' => (clone $base)->where('status', 'pending')->count(),
            'active' => (clone $base)->where('status', 'active')->whereHas('periods', fn ($q) => $q->whereDate('valid_from', '<=', today('UTC'))->whereDate('valid_until', '>=', today('UTC')))->count()];
        $query = $base->with(['organization', 'periods', 'integrityAudit'])
            ->when($filters['q'] ?? null, fn ($q, $value) => $q->where(fn ($sub) => $sub->where('full_name', 'like', '%'.$value.'%')
                ->orWhere('latin_name', 'like', '%'.$value.'%')->orWhere('membership_number', 'like', '%'.$value.'%')->orWhere('record_uuid', $value)))
            ->when($filters['organization_id'] ?? null, fn ($q, $value) => $q->where('organization_id', $value));
        $state = $filters['status'] ?? null;
        if (in_array($state, ['active', 'scheduled', 'expired'], true)) {
            $query->where('status', 'active');
            $current = fn ($q) => $q->whereDate('valid_from', '<=', today('UTC'))->whereDate('valid_until', '>=', today('UTC'));
            if ($state === 'active') { $query->whereHas('periods', $current); }
            elseif ($state === 'scheduled') { $query->whereDoesntHave('periods', $current)->whereHas('periods', fn ($q) => $q->whereDate('valid_from', '>', today('UTC'))); }
            else { $query->whereDoesntHave('periods', fn ($q) => $q->whereDate('valid_until', '>=', today('UTC'))); }
        } elseif ($state) { $query->where('status', $state); }
        $memberships = $query->latest('id')->paginate(20)->withQueryString();
        $checks = $memberships->getCollection()->mapWithKeys(fn ($record) => [$record->id => $this->registry()->verify($record)]);
        return view('control.memberships.index', compact('memberships', 'organizations', 'stats', 'checks'));
    }

    public function create(Request $request): View
    {
        $this->registry()->requirePermission($request->user(), 'memberships.manage');
        return view('control.memberships.form', ['membership' => new Membership(['preferred_locale' => app()->getLocale()]),
            'organizations' => $this->organizations($request)->where('status', 'active')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $record = $this->registry()->create($request->user(), $this->profile($request, false));
        return redirect()->route('memberships.show', ['locale' => app()->getLocale(), 'membership' => $record->id])->with('success', trans('memberships.saved'));
    }

    public function edit(Request $request): View
    {
        $this->registry()->requirePermission($request->user(), 'memberships.manage');
        $membership = $this->record($request);
        abort_unless(in_array($membership->status, ['draft', 'active', 'suspended'], true), 403);
        abort_unless($this->registry()->verify($membership), 409, trans('memberships.errors.integrity'));
        return view('control.memberships.form', ['membership' => $membership, 'organizations' => $this->organizations($request)]);
    }

    public function update(Request $request): RedirectResponse
    {
        $record = $this->record($request);
        $data = $this->profile($request, true);
        $this->registry()->update($request->user(), (int) $record->id, (int) $data['lock_version'], $data);
        return redirect()->route('memberships.show', ['locale' => app()->getLocale(), 'membership' => $record->id])->with('success', trans('memberships.saved'));
    }

    public function correct(Request $request): View
    {
        $this->registry()->requirePermission($request->user(), 'memberships.correct');
        $membership = $this->record($request);
        abort_unless(in_array($membership->status, ['active', 'suspended'], true), 409, trans('memberships.errors.correction_state'));
        abort_unless($this->registry()->verify($membership), 409, trans('memberships.errors.integrity'));

        return view('control.memberships.correct', compact('membership'));
    }

    public function storeCorrection(Request $request): RedirectResponse
    {
        $membership = $this->record($request);
        $data = $request->validate([
            'lock_version' => ['required', 'integer', 'min:1'],
            'full_name' => ['required', 'string', 'max:255'],
            'latin_name' => ['nullable', 'string', 'max:255'],
            'professional_title' => ['nullable', 'string', 'max:160'],
            'reason' => ['required', 'string', 'max:1500'],
        ]);
        $this->registry()->correctIdentity($request->user(), (int) $membership->id, (int) $data['lock_version'], $data);

        return redirect()->route('memberships.show', ['locale' => app()->getLocale(), 'membership' => $membership->id])
            ->with('success', trans('memberships.correction_saved'));
    }

    public function show(Request $request): View
    {
        $membership = $this->record($request);
        $integrity = $this->registry()->verify($membership);
        $periodChecks = $membership->periods->mapWithKeys(fn ($period) => [$period->id => $this->registry()->verifyPeriod($period)]);
        $history = AuditLog::query()->where('auditable_type', $membership->getMorphClass())->where('auditable_id', $membership->id)
            ->with('actor:id,name')->latest('sequence_number')->paginate(15);
        $reasons = [];
        foreach ($history as $event) {
            try { $reasons[$event->id] = isset($event->metadata['reason_encrypted']) ? Crypt::decryptString($event->metadata['reason_encrypted']) : null; }
            catch (Throwable) { $reasons[$event->id] = trans('memberships.unreadable'); }
        }
        return view('control.memberships.show', compact('membership', 'integrity', 'periodChecks', 'history', 'reasons'));
    }

    public function transition(Request $request): RedirectResponse
    {
        $record = $this->record($request);
        $action = (string) $request->route('action');
        $data = $request->validate(['lock_version' => ['required', 'integer', 'min:1'],
            'reason' => [$action === 'submit' ? 'nullable' : 'required', 'string', 'max:1500'],
            'valid_from' => [in_array($action, ['approve', 'renew'], true) ? 'required' : 'nullable', 'date_format:Y-m-d'],
            'valid_until' => [in_array($action, ['approve', 'renew'], true) ? 'required' : 'nullable', 'date_format:Y-m-d', 'after_or_equal:valid_from']]);
        $this->registry()->transition($request->user(), (int) $record->id, (int) $data['lock_version'], $action, $data);
        return redirect()->route('memberships.show', ['locale' => app()->getLocale(), 'membership' => $record->id])->with('success', trans('memberships.action_saved'));
    }

    private function profile(Request $request, bool $editing): array
    {
        if ($request->filled('country_code')) { $request->merge(['country_code' => strtoupper((string) $request->input('country_code'))]); }
        $rules = [
            'full_name' => ['required', 'string', 'max:255'], 'latin_name' => ['nullable', 'string', 'max:255'],
            'membership_type' => ['required', 'string', 'max:120'], 'professional_title' => ['nullable', 'string', 'max:160'],
            'country_code' => ['nullable', 'regex:/^[A-Z]{2}$/'], 'preferred_locale' => ['required', Rule::in(['ar', 'en', 'fr'])],
            'email' => ['nullable', 'email:rfc', 'max:254'], 'phone' => ['nullable', 'string', 'max:40'],
            'private_notes' => ['nullable', 'string', 'max:5000'],
        ];
        if ($editing) { $rules['lock_version'] = ['required', 'integer', 'min:1']; }
        else { $rules['organization_id'] = ['required', 'integer', Rule::in($this->organizations($request)->where('status', 'active')->pluck('id')->all())]; }
        return $request->validate($rules);
    }
}
