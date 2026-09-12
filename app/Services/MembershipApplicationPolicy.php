<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MembershipPaymentMethod;
use App\Models\MembershipProfessionalTitle;
use App\Models\MembershipSubscriptionPlan;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Throwable;

final class MembershipApplicationPolicy
{
    public const TERMS_VERSION = 'IUOAMC-MEMBERSHIP-TERMS-2026-09-12-v3';

    public const PRIVACY_VERSION = 'IUOAMC-MEMBERSHIP-PRIVACY-2026-09-12-v1';

    public const CURRENCY = 'GBP';

    /** @var array<string, string> */
    private const CATEGORIES = [
        'general' => 'General Membership',
        'professional' => 'Professional Membership',
        'elite' => 'Elite Membership',
        'international_expert' => 'International Expert Membership',
    ];

    /** @var array<int, int> Fees in pence. */
    private const DEFAULT_TERM_FEES = [
        1 => 25000,
        2 => 45000,
        5 => 110000,
        10 => 210000,
    ];

    /** @var array<string, string> */
    private const DEFAULT_TITLES = [
        'master-chef' => 'Master Chef',
        'executive-chef' => 'Executive Chef',
        'chef' => 'Chef',
        'pastry-chef' => 'Pastry Chef',
        'culinary-judge' => 'Culinary Judge',
        'culinary-trainer' => 'Culinary Trainer',
        'culinary-researcher' => 'Culinary Researcher',
        'food-safety-specialist' => 'Food Safety Specialist',
        'hospitality-professional' => 'Hospitality Professional',
        'gastronomy-specialist' => 'Gastronomy Specialist',
    ];

    /** @var array<string, string> */
    private const DEFAULT_PAYMENT_METHODS = [
        'stripe' => 'Stripe (card)',
        'wise' => 'Wise',
        'cash' => 'Cash',
        'bank-transfer' => 'Bank transfer',
        'western-union' => 'Western Union',
        'complimentary-request' => 'Complimentary / fee-waiver request',
    ];

    /** @var array<string, int> */
    private const FEE_ALLOCATION = [
        'file_review' => 35,
        'identity_verification' => 20,
        'eligibility_assessment' => 15,
        'registration' => 15,
        'credentials' => 10,
        'account_support' => 5,
    ];

    /** @return array<string, string> */
    public function categories(): array
    {
        return self::CATEGORIES;
    }

    /** @return list<string> */
    public function categoryCodes(): array
    {
        return array_keys(self::CATEGORIES);
    }

    /** @return list<string> */
    public function categoryNames(): array
    {
        return array_values(self::CATEGORIES);
    }

    public function categoryName(string $code): string
    {
        return self::CATEGORIES[$code]
            ?? throw new InvalidArgumentException('Unsupported membership category.');
    }

    public function categoryCodeForName(?string $name): ?string
    {
        $code = array_search($name, self::CATEGORIES, true);

        return is_string($code) ? $code : null;
    }

    /** @return array<int, int> */
    public function termFees(): array
    {
        if ($this->tableAvailable('membership_subscription_plans')) {
            $fees = MembershipSubscriptionPlan::query()->where('is_active', true)
                ->orderBy('sort_order')->orderBy('years')->pluck('fee_pence', 'years')->all();
            if ($fees !== []) {
                return array_map('intval', $fees);
            }
        }

        return self::DEFAULT_TERM_FEES;
    }

    /** @return list<int> */
    public function termYears(): array
    {
        return array_map('intval', array_keys($this->termFees()));
    }

    public function feePence(int $years): int
    {
        return $this->termFees()[$years]
            ?? throw new InvalidArgumentException('Unsupported membership term.');
    }

    /** @return array<string, string> */
    public function professionalTitles(?string $locale = null): array
    {
        if ($this->tableAvailable('membership_professional_titles')) {
            $titles = MembershipProfessionalTitle::query()->where('is_active', true)
                ->orderBy('sort_order')->orderBy('id')->get();
            if ($titles->isNotEmpty()) {
                return $titles->mapWithKeys(fn (MembershipProfessionalTitle $title): array => [
                    $title->code => $title->localizedName($locale),
                ])->all();
            }
        }

        return self::DEFAULT_TITLES;
    }

    /** @return list<string> */
    public function professionalTitleCodes(): array
    {
        return array_keys($this->professionalTitles('en'));
    }

    public function professionalTitleName(string $code): string
    {
        if ($this->tableAvailable('membership_professional_titles')) {
            $name = MembershipProfessionalTitle::query()->where('code', $code)->where('is_active', true)->value('name_en');
            if (is_string($name) && $name !== '') {
                return $name;
            }
        }

        return self::DEFAULT_TITLES[$code]
            ?? throw new InvalidArgumentException('Unsupported professional title.');
    }

    public function professionalTitleCodeForName(?string $name): ?string
    {
        if (! is_string($name) || trim($name) === '') {
            return null;
        }
        if ($this->tableAvailable('membership_professional_titles')) {
            $code = MembershipProfessionalTitle::query()->where(function ($query) use ($name): void {
                $query->where('name_en', $name)->orWhere('name_ar', $name)->orWhere('name_fr', $name);
            })->value('code');
            if (is_string($code)) {
                return $code;
            }
        }
        $code = array_search($name, self::DEFAULT_TITLES, true);

        return is_string($code) ? $code : null;
    }

    /** @return array<string, string> */
    public function paymentMethods(?string $locale = null): array
    {
        if ($this->tableAvailable('membership_payment_methods')) {
            $methods = MembershipPaymentMethod::query()->where('is_active', true)
                ->orderBy('sort_order')->orderBy('id')->get();
            if ($methods->isNotEmpty()) {
                return $methods->mapWithKeys(fn (MembershipPaymentMethod $method): array => [
                    $method->code => $method->localizedName($locale),
                ])->all();
            }
        }

        return self::DEFAULT_PAYMENT_METHODS;
    }

    /** @return list<string> */
    public function paymentMethodCodes(): array
    {
        return array_keys($this->paymentMethods('en'));
    }

    public function paymentMethodRequiresWaiverReason(string $code): bool
    {
        if ($this->tableAvailable('membership_payment_methods')) {
            return (bool) MembershipPaymentMethod::query()->where('code', $code)
                ->where('is_active', true)->value('requires_waiver_reason');
        }

        return $code === 'complimentary-request';
    }

    /** @return array<string, int> */
    public function feeAllocation(): array
    {
        return self::FEE_ALLOCATION;
    }

    /**
     * @param list<string> $completedStages
     * @return array{earned_service_pence: int, external_costs_pence: int, refund_pence: int}
     */
    public function refundCalculation(
        int $standardFeePence,
        int $paidPence,
        array $completedStages,
        int $externalCostsPence = 0
    ): array {
        if ($standardFeePence < 0 || $paidPence < 0 || $externalCostsPence < 0) {
            throw new InvalidArgumentException('Refund values cannot be negative.');
        }

        $earnedServicePence = 0;

        foreach (array_unique($completedStages) as $stage) {
            if (! array_key_exists($stage, self::FEE_ALLOCATION)) {
                throw new InvalidArgumentException('Unsupported membership service stage.');
            }

            $earnedServicePence += (int) round(
                $standardFeePence * self::FEE_ALLOCATION[$stage] / 100
            );
        }

        return [
            'earned_service_pence' => $earnedServicePence,
            'external_costs_pence' => $externalCostsPence,
            'refund_pence' => max(0, $paidPence - $earnedServicePence - $externalCostsPence),
        ];
    }

    private function tableAvailable(string $table): bool
    {
        try {
            return function_exists('app') && app()->bound('db') && Schema::hasTable($table);
        } catch (Throwable) {
            return false;
        }
    }
}
