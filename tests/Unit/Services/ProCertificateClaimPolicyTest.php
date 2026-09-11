<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\ProCertificateClaimPolicy;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class ProCertificateClaimPolicyTest extends TestCase
{
    public function test_completion_does_not_claim_professional_accreditation(): void
    {
        $this->expectException(ValidationException::class);

        (new ProCertificateClaimPolicy())->enforce([
            'credential_basis' => ProCertificateClaimPolicy::PROGRAMME_COMPLETION,
            'certificate_title' => 'Certified International Culinary Judge',
            'statement' => 'Completed the eight-month training programme.',
        ]);
    }

    public function test_completion_accepts_an_accurate_training_statement(): void
    {
        (new ProCertificateClaimPolicy())->enforce([
            'credential_basis' => ProCertificateClaimPolicy::PROGRAMME_COMPLETION,
            'certificate_title' => 'Certificate of Programme Completion',
            'statement' => 'Completed the eight-month Level One programme and its assessments.',
        ]);

        self::assertTrue(true);
    }

    public function test_accreditation_requires_a_documented_decision(): void
    {
        $this->expectException(ValidationException::class);

        (new ProCertificateClaimPolicy())->enforce([
            'credential_basis' => ProCertificateClaimPolicy::PROFESSIONAL_ACCREDITATION,
            'certificate_title' => 'Accredited International Culinary Judge',
            'statement' => 'Professional accreditation granted.',
        ]);
    }

    public function test_accreditation_accepts_a_reference_and_decision_date(): void
    {
        (new ProCertificateClaimPolicy())->enforce([
            'credential_basis' => ProCertificateClaimPolicy::PROFESSIONAL_ACCREDITATION,
            'accreditation_reference' => 'WSACA-ACC-2026-0001',
            'accreditation_date' => '2026-09-11',
            'certificate_title' => 'Accredited International Culinary Judge',
            'statement' => 'Professional accreditation granted.',
        ]);

        self::assertTrue(true);
    }
}
