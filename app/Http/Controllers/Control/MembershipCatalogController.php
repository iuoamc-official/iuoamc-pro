<?php

declare(strict_types=1);

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Models\MembershipPaymentMethod;
use App\Models\MembershipProfessionalTitle;
use App\Models\MembershipSubscriptionPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class MembershipCatalogController extends Controller
{
    public function index(): View
    {
        return view('control.memberships.settings', [
            'titles' => MembershipProfessionalTitle::query()->orderBy('sort_order')->orderBy('id')->get(),
            'plans' => MembershipSubscriptionPlan::query()->orderBy('sort_order')->orderBy('years')->get(),
            'paymentMethods' => MembershipPaymentMethod::query()->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function storeTitle(Request $request): RedirectResponse
    {
        $data = $this->localizedData($request);
        MembershipProfessionalTitle::query()->create($data + [
            'code' => $this->uniqueCode(MembershipProfessionalTitle::class, $data['name_en']),
            'created_by' => (int) $request->user()->id,
            'updated_by' => (int) $request->user()->id,
        ]);

        return $this->back('memberships.catalog_saved');
    }

    public function updateTitle(Request $request, string $locale, MembershipProfessionalTitle $title): RedirectResponse
    {
        $title->update($this->localizedData($request) + ['updated_by' => (int) $request->user()->id]);

        return $this->back('memberships.catalog_saved');
    }

    public function storePlan(Request $request): RedirectResponse
    {
        $data = $this->planData($request);
        MembershipSubscriptionPlan::query()->create($data + [
            'currency' => 'GBP', 'created_by' => (int) $request->user()->id, 'updated_by' => (int) $request->user()->id,
        ]);

        return $this->back('memberships.catalog_saved');
    }

    public function updatePlan(Request $request, string $locale, MembershipSubscriptionPlan $plan): RedirectResponse
    {
        $plan->update($this->planData($request, $plan) + ['updated_by' => (int) $request->user()->id]);

        return $this->back('memberships.catalog_saved');
    }

    public function storePaymentMethod(Request $request): RedirectResponse
    {
        $data = $this->localizedData($request, true);
        MembershipPaymentMethod::query()->create($data + [
            'code' => $this->uniqueCode(MembershipPaymentMethod::class, $data['name_en']),
            'created_by' => (int) $request->user()->id,
            'updated_by' => (int) $request->user()->id,
        ]);

        return $this->back('memberships.catalog_saved');
    }

    public function updatePaymentMethod(Request $request, string $locale, MembershipPaymentMethod $paymentMethod): RedirectResponse
    {
        $paymentMethod->update($this->localizedData($request, true) + ['updated_by' => (int) $request->user()->id]);

        return $this->back('memberships.catalog_saved');
    }

    /** @return array<string, mixed> */
    private function localizedData(Request $request, bool $payment = false): array
    {
        $rules = [
            'name_ar' => ['required', 'string', 'max:160'],
            'name_en' => ['required', 'string', 'max:160'],
            'name_fr' => ['required', 'string', 'max:160'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ];
        if ($payment) {
            $rules['requires_waiver_reason'] = ['nullable', 'boolean'];
        }
        $data = $request->validate($rules);
        $data['is_active'] = $request->boolean('is_active');
        if ($payment) {
            $data['requires_waiver_reason'] = $request->boolean('requires_waiver_reason');
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function planData(Request $request, ?MembershipSubscriptionPlan $plan = null): array
    {
        $data = $request->validate([
            'years' => ['required', 'integer', 'min:1', 'max:50', Rule::unique('membership_subscription_plans', 'years')->ignore($plan?->id)],
            'fee_amount' => ['required', 'numeric', 'min:0', 'max:100000'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return [
            'years' => (int) $data['years'],
            'fee_pence' => (int) round((float) $data['fee_amount'] * 100),
            'sort_order' => (int) $data['sort_order'],
            'is_active' => $request->boolean('is_active'),
        ];
    }

    private function uniqueCode(string $model, string $name): string
    {
        $base = Str::slug($name) ?: 'option';
        $code = $base;
        $suffix = 2;
        while ($model::query()->where('code', $code)->exists()) {
            $code = $base.'-'.$suffix++;
        }

        return $code;
    }

    private function back(string $message): RedirectResponse
    {
        return redirect()->route('memberships.settings', ['locale' => app()->getLocale()])->with('success', trans($message));
    }
}
