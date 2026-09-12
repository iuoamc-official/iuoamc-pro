<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\MembershipApplicationPolicy;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MembershipApplicationPolicyTest extends TestCase
{
    public function test_published_terms_and_fee_allocation_are_exact(): void
    {
        $policy = new MembershipApplicationPolicy();

        self::assertSame([1 => 25000, 2 => 45000, 5 => 110000, 10 => 210000], $policy->termFees());
        self::assertSame(100, array_sum($policy->feeAllocation()));
        self::assertSame(
            ['general', 'professional', 'elite', 'international_expert'],
            $policy->categoryCodes()
        );
        self::assertContains('master-chef', $policy->professionalTitleCodes());
        self::assertContains('complimentary-request', $policy->paymentMethodCodes());
        self::assertTrue($policy->paymentMethodRequiresWaiverReason('complimentary-request'));
        self::assertFalse($policy->paymentMethodRequiresWaiverReason('stripe'));
    }

    public function test_refund_uses_standard_fee_before_discount(): void
    {
        $result = (new MembershipApplicationPolicy())->refundCalculation(
            standardFeePence: 25000,
            paidPence: 20000,
            completedStages: ['file_review']
        );

        self::assertSame(8750, $result['earned_service_pence']);
        self::assertSame(11250, $result['refund_pence']);
    }

    public function test_refund_is_never_negative_and_duplicate_stages_are_not_counted_twice(): void
    {
        $result = (new MembershipApplicationPolicy())->refundCalculation(
            standardFeePence: 25000,
            paidPence: 10000,
            completedStages: ['file_review', 'file_review', 'identity_verification'],
            externalCostsPence: 500
        );

        self::assertSame(13750, $result['earned_service_pence']);
        self::assertSame(0, $result['refund_pence']);
    }

    public function test_unknown_service_stage_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new MembershipApplicationPolicy())->refundCalculation(25000, 25000, ['unknown']);
    }
}
