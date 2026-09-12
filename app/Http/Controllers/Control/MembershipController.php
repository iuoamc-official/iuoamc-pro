<?php
declare(strict_types=1);

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\MembershipCredential;
use App\Models\Organization;
use App\Services\InstitutionalAccess;
use App\Services\MembershipCredentialRegistry;
use App\Services\MembershipApplicationPolicy;
use App\Services\MembershipRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class MembershipController extends Controller
{
    private function registry(): MembershipRegistry { return app(MembershipRegistry::class); }
    private function credentials(): MembershipCredentialRegistry { return app(MembershipCredentialRegistry::class); }
    private function applicationPolicy(): MembershipApplicationPolicy { return app(MembershipApplicationPolicy::class); }

    private function commercialOptions(): array
    {
        return [
            'membershipCategories' => collect($this->applicationPolicy()->categories())->mapWithKeys(
                fn (string $name, string $code): array => [$name => trans('account.membership_categories.'.$code)]
            ),
            'membershipTermFees' => $this->applicationPolicy()->termFees(),
            'memberTitles' => $this->applicationPolicy()->professionalTitles(app()->getLocale()),
            'paymentMethods' => $this->applicationPolicy()->paymentMethods(app()->getLocale()),
            'waiverPaymentMethods' => collect($this->applicationPolicy()->paymentMethodCodes())
                ->filter(fn (string $code): bool => $this->applicationPolicy()->paymentMethodRequiresWaiverReason($code))->values(),
        ];
    }

    private function organizations(Request $request)
    {
        return app(InstitutionalAccess::class)->constrainOrganizations(Organization::query(), $request->user())
            ->orderBy('display_name')->get(['id', 'display_name', 'status']);
    }

    private function record(Request $request): Membership
    {
        // Read named route parameters explicitly; locale is another route parameter.
        return $this->registry()->scoped($request->user())->with(['organization', 'integrityAudit', 'periods.audit', 'periods.approver', 'application.integrityAudit', 'credentials.integrityAudit'])
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
                ->orWhere('latin_name', 'like', '%'.$value.'%')->orWhere('membership_number', 'like', '%'.$value.'%')
                ->orWhereHas('credentials', fn ($credentialQuery) => $credentialQuery->where('membership_number', 'like', '%'.$value.'%'))
                ->orWhere('record_uuid', $value)))
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
        return view('control.memberships.form', [
            'membership' => new Membership(['preferred_locale' => app()->getLocale()]),
            'organizations' => $this->organizations($request)->where('status', 'active'),
            ...$this->commercialOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $profile = $this->profile($request, false);
        $application = $this->applicationData($request, true);
        $record = DB::transaction(function () use ($request, $profile, $application): Membership {
            $membership = $this->registry()->create($request->user(), $profile);
            $this->credentials()->saveApplication(
                $request->user(), $membership, $application, $request->file('photo')
            );

            return $membership;
        }, 3);

        return redirect()->route('memberships.show', ['locale' => app()->getLocale(), 'membership' => $record->id])->with('success', trans('memberships.saved'));
       }

    public function edit(Request $request): View
    {
        $this->registry()->requirePermission($request->user(), 'memberships.manage');
        $membership = $this->record($request);
        abort_unless(in_array($membership->status, ['draft', 'active', 'suspended'], true), 403);
        abort_unless($this->registry()->verify($membership), 409, trans('memberships.errors.integrity'));
        return view('control.memberships.form', [
            'membership' => $membership,
            'organizations' => $this->organizations($request),
            ...$this->commercialOptions(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $record = $this->record($request);
        $data = $this->profile($request, true);
        $updated = $this->registry()->update($request->user(), (int) $record->id, (int) $data['lock_version'], $data);
        if ($record->status === 'draft') {
            $application = $this->applicationData($request, $record->application === null);
            $this->credentials()->saveApplication(
                $request->user(), $updated, $application, $request->file('photo')
            );
        }

        return redirect()->route('memberships.show', ['locale' => app()->getLocale(), 'membership' => $record->id])->with('success', trans('memberships.saved'));
    }

    public function correct(Request $request): View
    {
        $this->registry()->requirePermission($request->user(), 'memberships.correct');
        $membership = $this->record($request);
        abort_unless(in_array($membership->status, ['active', 'suspended'], true), 409, trans('memberships.errors.correction_state'));
        abort_unless($this->registry()->verify($membership), 409, trans('memberships.errors.integrity'));

        return view('control.memberships.correct', [
            'membership' => $membership,
            'membershipCategories' => collect($this->applicationPolicy()->categories())->mapWithKeys(
                fn (string $name, string $code): array => [$name => trans('account.membership_categories.'.$code)]
            ),
            'memberTitles' => $this->applicationPolicy()->professionalTitles(app()->getLocale()),
            'selectedMemberTitle' => $this->applicationPolicy()->professionalTitleCodeForName($membership->professional_title),
        ]);
    }

    public function storeCorrection(Request $request): RedirectResponse
    {
        $membership = $this->record($request);
        $data = $request->validate([
            'lock_version' => ['required', 'integer', 'min:1'],
            'full_name' => ['required', 'string', 'max:255'],
            'latin_name' => ['nullable', 'string', 'max:255'],
            'membership_type' => ['required', Rule::in($this->applicationPolicy()->categoryNames())],
            'member_title_code' => ['required', Rule::in($this->applicationPolicy()->professionalTitleCodes())],
            'replacement_membership_number' => [
                'nullable', 'string', 'max:80', 'regex:/\A[A-Za-z0-9][A-Za-z0-9.\/_-]{4,79}\z/D',
                Rule::unique('memberships', 'membership_number')->ignore($membership->id),
            ],
            'reason' => ['required', 'string', 'max:1500'],
        ]);
        $data['professional_title'] = $this->applicationPolicy()->professionalTitleName($data['member_title_code']);
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
        $application = $membership->application;
        $applicationReady = $integrity && $this->credentials()->ready($membership);
        $credentials = $this->credentials()->credentialsFor($membership);
        $credentialChecks = $credentials->mapWithKeys(
            fn (MembershipCredential $credential): array => [$credential->id => $this->credentials()->verifyCredential($credential)]
        );
        $today = now()->utc()->toDateString();
        $currentPeriod = $membership->periods->first(fn ($period): bool =>
            $period->valid_from->toDateString() <= $today && $period->valid_until->toDateString() >= $today
        );
        $canIssueCredentials = $integrity && $applicationReady
            && $membership->status === 'active'
            && $currentPeriod !== null
            && ! $credentials->contains('membership_period_id', $currentPeriod->id);

        return view('control.memberships.show', compact(
            'membership', 'integrity', 'periodChecks', 'history', 'reasons',
            'application', 'applicationReady', 'credentials', 'credentialChecks', 'canIssueCredentials'
        ));
    }

    public function application(Request $request): View
    {
        $this->registry()->requirePermission($request->user(), 'memberships.manage');
        $membership = $this->record($request);
        abort_unless($this->registry()->verify($membership), 409, trans('memberships.errors.integrity'));
        abort_unless(in_array($membership->status, ['draft', 'active', 'suspended'], true), 409);
        abort_if($membership->credentials->isNotEmpty(), 409, trans('memberships.errors.application_locked'));

        return view('control.memberships.application', [
            'membership' => $membership,
            'application' => $membership->application,
            ...$this->commercialOptions(),
        ]);
    }

    public function updateApplication(Request $request): RedirectResponse
    {
        $membership = $this->record($request);
        $data = $this->applicationData($request, $membership->application === null);
        $this->credentials()->saveApplication(
            $request->user(), $membership, $data, $request->file('photo')
        );

        return redirect()->route('memberships.show', ['locale' => app()->getLocale(), 'membership' => $membership->id])
            ->with('success', trans('memberships.application_saved'));
    }

    public function issueCredentials(Request $request): RedirectResponse
    {
        $membership = $this->record($request);
        $this->credentials()->issue($request->user(), $membership);

        return redirect()->route('memberships.show', ['locale' => app()->getLocale(), 'membership' => $membership->id])
            ->with('success', trans('memberships.credentials_issued'));
    }

    public function downloadCredential(Request $request): BinaryFileResponse
    {
        $membership = $this->record($request);
        $credential = MembershipCredential::query()
            ->where('membership_id', $membership->id)
            ->findOrFail((int) $request->route('credential'));
        $kind = (string) $request->route('kind');
        $path = $this->credentials()->downloadPath($credential, $kind);
        $filename = preg_replace('/[^A-Za-z0-9_-]/', '-', (string) $credential->membership_number)
            .'-v'.$credential->version.'-'.$kind.'.pdf';

        return response()->download($path, $filename, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
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

    private function applicationData(Request $request, bool $photoRequired): array
    {
        if (! $request->filled('member_title_code')) {
            $routeMembership = $request->route('membership');
            $membership = $routeMembership instanceof Membership
                ? $routeMembership
                : (is_numeric($routeMembership) ? Membership::query()->find((int) $routeMembership) : null);
            $titleCode = $this->applicationPolicy()->professionalTitleCodeForName($membership?->professional_title);
            if ($titleCode !== null) {
                $request->merge(['member_title_code' => $titleCode]);
            }
        }
        if ($request->filled('nationality_code')) {
            $request->merge(['nationality_code' => strtoupper(trim((string) $request->input('nationality_code')))]);
        }
        if ($request->filled('residence_country_code')) {
            $request->merge(['residence_country_code' => strtoupper(trim((string) $request->input('residence_country_code')))]);
        }

        $validated = $request->validate([
            'membership_term_years' => ['required', 'integer', Rule::in($this->applicationPolicy()->termYears())],
            'member_title_code' => ['required', Rule::in($this->applicationPolicy()->professionalTitleCodes())],
            'requested_payment_method_code' => ['required', Rule::in($this->applicationPolicy()->paymentMethodCodes())],
            'fee_waiver_reason' => ['nullable', 'string', 'min:20', 'max:1500'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'date_of_birth' => ['required', 'date_format:Y-m-d', 'before:today'],
            'nationality_code' => ['required', 'regex:/^[A-Z]{2}$/'],
            'address' => ['required', 'string', 'max:1000'],
            'city' => ['required', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'residence_country_code' => ['required', 'regex:/^[A-Z]{2}$/'],
            'identification_type' => ['required', 'string', 'max:60'],
            'identification_number' => ['required', 'string', 'max:120'],
            'qualifications' => ['nullable', 'string', 'max:3000'],
            'photo' => [$photoRequired ? 'required' : 'nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'application_consent' => ['accepted'],
            'terms_consent' => ['accepted'],
            'service_start_choice' => ['required', Rule::in(['immediate', 'after_cooling_off'])],
        ]);

        $membershipType = (string) $request->input('membership_type', '');
        $routeMembership = $request->route('membership');
        if ($membershipType === '' && $routeMembership instanceof Membership) {
            $membershipType = (string) $routeMembership->membership_type;
        } elseif ($membershipType === '' && is_numeric($routeMembership)) {
            $membershipType = (string) Membership::query()->whereKey((int) $routeMembership)->value('membership_type');
        }
        $categoryCode = $this->applicationPolicy()->categoryCodeForName($membershipType);
        if ($categoryCode === null) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'membership_type' => trans('memberships.membership_category_error'),
            ]);
        }

        $validated['membership_category_code'] = $categoryCode;
        $validated['discount_pence'] = (int) round((float) ($validated['discount_amount'] ?? 0) * 100);

        return $validated;
    }

    private function profile(Request $request, bool $editing): array
    {
        if ($request->filled('country_code')) { $request->merge(['country_code' => strtoupper((string) $request->input('country_code'))]); }
        $allowedMembershipTypes = $this->applicationPolicy()->categoryNames();
        if ($editing && is_numeric($request->route('membership'))) {
            $currentType = Membership::query()->whereKey((int) $request->route('membership'))->value('membership_type');
            if (is_string($currentType) && $currentType !== '') {
                $allowedMembershipTypes[] = $currentType;
            }
        }
        $rules = [
            'full_name' => ['required', 'string', 'max:255'], 'latin_name' => ['nullable', 'string', 'max:255'],
            'membership_type' => ['required', Rule::in(array_unique($allowedMembershipTypes))],
            'member_title_code' => ['nullable', Rule::in($this->applicationPolicy()->professionalTitleCodes())],
            'professional_title' => ['nullable', 'string', 'max:160'],
            'country_code' => ['nullable', 'regex:/^[A-Z]{2}$/'], 'preferred_locale' => ['required', Rule::in(['ar', 'en', 'fr'])],
            'email' => ['nullable', 'email:rfc', 'max:254'], 'phone' => ['nullable', 'string', 'max:40'],
            'private_notes' => ['nullable', 'string', 'max:5000'],
        ];
        if ($editing) { $rules['lock_version'] = ['required', 'integer', 'min:1']; }
        else { $rules['organization_id'] = ['required', 'integer', Rule::in($this->organizations($request)->where('status', 'active')->pluck('id')->all())]; }
        $data = $request->validate($rules);
        if (isset($data['member_title_code'])) {
            $data['professional_title'] = $this->applicationPolicy()->professionalTitleName($data['member_title_code']);
        } elseif (! $editing || (string) ($data['professional_title'] ?? '') === '') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'member_title_code' => trans('memberships.professional_title_required'),
            ]);
        }
        unset($data['member_title_code']);

        return $data;
    }
}
