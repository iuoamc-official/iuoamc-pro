<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

final class MembershipApplicationPolicy
{
    public const TERMS_VERSION = 'IUOAMC-MEMBERSHIP-TERMS-2026-09-12-v2';

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
    private const TERM_FEES = [
        1 => 25000,
        2 => 45000,
        5 => 110000,
        10 => 210000,
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
        return self::TERM_FEES;
    }

    /** @return list<int> */
    public function termYears(): array
    {
        return array_keys(self::TERM_FEES);
    }

    public function feePence(int $years): int
    {
        return self::TERM_FEES[$years]
            ?? throw new InvalidArgumentException('Unsupported membership term.');
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
}
