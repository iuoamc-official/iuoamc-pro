<?php
declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\ProCertificate;
use App\Services\ProCertificateWorkspace;
use PHPUnit\Framework\TestCase;

final class ProCertificateWorkspaceTest extends TestCase
{
    public function test_it_preserves_older_recipient_programme_records_in_the_archive(): void
    {
        $older = $this->certificate(10, 'Student Alpha', 'Sensory Evaluation');
        $newer = $this->certificate(20, '  STUDENT   ALPHA ', 'sensory evaluation');
        $other = $this->certificate(15, 'Student Beta', 'Sensory Evaluation');

        $partition = (new ProCertificateWorkspace())->partition(collect([$older, $newer, $other]));

        self::assertSame([20, 15], $partition['current']->pluck('id')->all());
        self::assertSame([10], $partition['archive']->pluck('id')->all());
    }

    public function test_it_keeps_different_programmes_and_organizations_current(): void
    {
        $first = $this->certificate(10, 'Same Student', 'Programme A', 1);
        $second = $this->certificate(20, 'Same Student', 'Programme B', 1);
        $third = $this->certificate(30, 'Same Student', 'Programme A', 2);

        $partition = (new ProCertificateWorkspace())->partition(collect([$first, $second, $third]));

        self::assertSame([30, 20, 10], $partition['current']->pluck('id')->all());
        self::assertCount(0, $partition['archive']);
    }

    private function certificate(int $id, string $recipient, string $programme, int $organizationId = 1): ProCertificate
    {
        $certificate = new ProCertificate([
            'organization_id' => $organizationId,
            'certificate_type' => 'professional_master',
            'program_title' => $programme,
            'recipient_name' => $recipient,
        ]);
        $certificate->setAttribute('id', $id);

        return $certificate;
    }
}
