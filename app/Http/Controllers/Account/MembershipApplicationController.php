<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\Organization;
use App\Services\AccountRecordAccess;
use App\Services\MembershipCredentialRegistry;
use App\Services\MembershipApplicationPolicy;
use App\Services\MembershipRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class MembershipApplicationController extends Controller
{
    public function create(Request $request): View
    {
        $policy = app(MembershipApplicationPolicy::class);

        return view('account.membership-application', [
            'organizations' => Organization::query()->where('status', 'active')->orderByDesc('is_root')
                ->orderBy('display_name')->get(['id', 'display_name', 'legal_name']),
            'membershipCategories' => collect($policy->categories())->mapWithKeys(
                fn (string $name, string $code): array => [$code => __('account.membership_categories.'.$code)]
            ),
            'membershipTermFees' => $policy->termFees(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $organizations = Organization::query()->where('status', 'active')->pluck('id')->all();
        foreach (['country_code', 'nationality_code', 'residence_country_code'] as $field) {
            if ($request->filled($field)) {
                $request->merge([$field => strtoupper(trim((string) $request->input($field)))]);
            }
        }
        $data = $request->validate([
            'organization_id' => ['required', 'integer', Rule::in($organizations)],
            'membership_category_code' => ['required', Rule::in(app(MembershipApplicationPolicy::class)->categoryCodes())],
            'membership_term_years' => ['required', 'integer', Rule::in(app(MembershipApplicationPolicy::class)->termYears())],
            'full_name' => ['required', 'string', 'max:255'],
            'latin_name' => ['nullable', 'string', 'max:255'],
            'professional_title' => ['nullable', 'string', 'max:160'],
            'country_code' => ['required', 'regex:/^[A-Z]{2}$/'],
            'phone' => ['required', 'string', 'max:40'],
            'date_of_birth' => ['required', 'date_format:Y-m-d', 'before:today'],
            'nationality_code' => ['required', 'regex:/^[A-Z]{2}$/'],
            'address' => ['required', 'string', 'max:1000'],
            'city' => ['required', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'residence_country_code' => ['required', 'regex:/^[A-Z]{2}$/'],
            'identification_type' => ['required', 'string', 'max:60'],
            'identification_number' => ['required', 'string', 'max:120'],
            'qualifications' => ['nullable', 'string', 'max:3000'],
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'application_consent' => ['accepted'],
            'terms_consent' => ['accepted'],
            'service_start_choice' => ['required', Rule::in(['immediate', 'after_cooling_off'])],
        ]);
        $membershipType = app(MembershipApplicationPolicy::class)->categoryName($data['membership_category_code']);
        $user = $request->user();
        $duplicate = Membership::query()->where('organization_id', $data['organization_id'])
            ->where('membership_type', $membershipType)
            ->whereIn('status', ['draft', 'pending', 'active', 'suspended'])
            ->get(['id', 'email'])->contains(function (Membership $membership) use ($user): bool {
                return hash_equals(
                    AccountRecordAccess::normalizeEmail($user->email),
                    AccountRecordAccess::normalizeEmail($membership->email)
                );
            });
        if ($duplicate) {
            throw ValidationException::withMessages(['membership_category_code' => __('account.duplicate_application')]);
        }

        DB::transaction(function () use ($user, $data, $request, $membershipType): void {
            $profile = [
                'organization_id' => (int) $data['organization_id'],
                'membership_type' => $membershipType,
                'full_name' => trim($data['full_name']),
                'latin_name' => trim((string) ($data['latin_name'] ?? '')) ?: null,
                'professional_title' => trim((string) ($data['professional_title'] ?? '')) ?: null,
                'country_code' => $data['country_code'],
                'preferred_locale' => app()->getLocale(),
                'email' => AccountRecordAccess::normalizeEmail($user->email),
                'phone' => trim($data['phone']),
                'private_notes' => null,
            ];
            $membership = app(MembershipRegistry::class)->createForVerifiedAccount($user, $profile);
            app(AccountRecordAccess::class)->claimMembership($user, $membership);
            app(MembershipCredentialRegistry::class)->saveApplicationForVerifiedAccount(
                $user,
                $membership,
                $data + ['application_consent' => true, 'terms_consent' => true],
                $request->file('photo')
            );
            app(MembershipRegistry::class)->submitForVerifiedAccount($user, $membership);
        }, 3);

        return redirect()->route('account.dashboard', ['locale' => app()->getLocale()])
            ->with('success', __('account.application_submitted'));
    }
}
