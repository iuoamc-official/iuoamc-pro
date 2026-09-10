<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ProMasterCertificatePdf;
use PHPUnit\Framework\TestCase;

final class ProMasterCertificateIpCodeTest extends TestCase
{
    public function testItExtractsTheRegisteredProgrammeIntellectualPropertyCode(): void
    {
        $statement = "Completed the professional programme. Final score: 96/100.\n"
            .'Programme registration: WICP-PRO-P-2026-b8e657806ac53e6cd5b38d72d34c5bad';

        self::assertSame(
            'WICP-PRO-P-2026-b8e657806ac53e6cd5b38d72d34c5bad',
            ProMasterCertificatePdf::programIpCodeFromStatement($statement)
        );
    }

    public function testItRejectsCertificateAndMalformedRegistrationCodes(): void
    {
        self::assertNull(ProMasterCertificatePdf::programIpCodeFromStatement(
            'WICP-PRO-C-2026-b8e657806ac53e6cd5b38d72d34c5bad'
        ));
        self::assertNull(ProMasterCertificatePdf::programIpCodeFromStatement(
            'WICP-PRO-P-2026-not-a-secure-registration'
        ));
        self::assertNull(ProMasterCertificatePdf::programIpCodeFromStatement(null));
    }
}
