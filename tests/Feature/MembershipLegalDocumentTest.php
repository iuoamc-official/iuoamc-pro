<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\MembershipApplicationPolicy;
use Tests\TestCase;

final class MembershipLegalDocumentTest extends TestCase
{
    public function test_arabic_terms_publish_fees_refund_basis_and_version(): void
    {
        $this->get('/ar/legal/membership-terms')
            ->assertOk()
            ->assertSee(MembershipApplicationPolicy::TERMS_VERSION)
            ->assertSee('£250.00')
            ->assertSee('£450.00')
            ->assertSee('£1,100.00')
            ->assertSee('£2,100.00')
            ->assertSee('35%')
            ->assertSee('الحد الأقصى لقيمة مرحلة')
            ->assertSee('الرسم الأساسي قبل الخصم')
            ->assertSee('لا ينشئ عضوية')
            ->assertSee('60 Tottenham Court Road')
            ->assertSee('نموذج الإلغاء القياسي')
            ->assertSee('وسيلة الدفع الأصلية');
    }

    public function test_english_privacy_notice_is_public_and_versioned(): void
    {
        $this->get('/en/legal/membership-privacy')
            ->assertOk()
            ->assertSee(MembershipApplicationPolicy::PRIVACY_VERSION)
            ->assertSee('ZB971358')
            ->assertSee('info@iuoamc.uk');
    }
}
