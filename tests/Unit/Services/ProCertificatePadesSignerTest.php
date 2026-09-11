<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\ProCertificatePadesSigner;
use Tests\TestCase;

final class ProCertificatePadesSignerTest extends TestCase
{
    public function test_readiness_reports_that_signing_is_disabled_by_default(): void
    {
        config()->set('certificates.pades.enabled', false);

        self::assertSame(
            ['ready' => false, 'reason' => 'PADES_NOT_ENABLED'],
            app(ProCertificatePadesSigner::class)->readiness(),
        );
    }

    public function test_it_applies_and_cryptographically_validates_a_real_pades_signature(): void
    {
        if ((string) env('IUOAMC_PADES_CI') !== '1') {
            self::markTestSkipped('The isolated PAdES integration environment is unavailable.');
        }

        config()->set('certificates.pades.enabled', true);
        $signer = app(ProCertificatePadesSigner::class);

        $readiness = $signer->readiness();
        self::assertTrue($readiness['ready'], (string) $readiness['reason']);
        $unsigned = file_get_contents(resource_path('certificates/master-a4-v1.3.1.pdf'));
        self::assertIsString($unsigned);

        $signed = $signer->sign($unsigned);

        self::assertSame('PAdES-B-B', $signed['profile']);
        self::assertSame('valid', $signed['status']);
        self::assertSame('IUOAMC_Authorized_Signature', $signed['field']);
        self::assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/D', $signed['certificate_sha256']);
        self::assertStringStartsWith('%PDF-', $signed['bytes']);
        self::assertGreaterThan(strlen($unsigned), strlen($signed['bytes']));
    }
}
