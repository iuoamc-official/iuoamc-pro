<?php
declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\ProCertificate;
use App\Services\ProCertificateWorkspace;
use Tests\TestCase;

final class ProCertificateWorkspaceTest extends TestCase
{
    public function test_it_preserves_older_recipient_programme_records_in_the_archive(): void
    {
        $older = $this->certificate(10, 'Student Alpha', 'Sensory and Restaurant Evaluation');
        $newer = $this->certificate(20, '  STUDENT   ALPHA ', 'Sensory Evaluation and Restaurant Assessment');
        $other = $this->certificate(15, 'Student Beta', 'Sensory Evaluation');

        $partition = (new ProCertificateWorkspace())->partition(collect([$older, $newer, $other]));

        self::assertSame([20, 15], $partition['current']->pluck('id')->all());
        self::assertSame([10], $partition['archive']->pluck('id')->all());
    }

    public function test_it_keeps_different_catalog_types_and_organizations_current(): void
    {
        $first = $this->certificate(10, 'Same Student', 'Programme A', 1, 5);
        $second = $this->certificate(20, 'Same Student', 'Programme B', 1, 6);
        $third = $this->certificate(30, 'Same Student', 'Programme A', 2, 5);

        $partition = (new ProCertificateWorkspace())->partition(collect([$first, $second, $third]));

        self::assertSame([30, 20, 10], $partition['current']->pluck('id')->all());
        self::assertCount(0, $partition['archive']);
    }

    public function test_it_keeps_separate_achievement_dates_current(): void
    {
        $first = $this->certificate(10, 'Same Student', 'Programme A', 1, 5, '2026-08-02');
        $second = $this->certificate(20, 'Same Student', 'Programme A', 1, 5, '2027-08-02');

        $partition = (new ProCertificateWorkspace())->partition(collect([$first, $second]));

        self::assertSame([20, 10], $partition['current']->pluck('id')->all());
        self::assertCount(0, $partition['archive']);
    }

    private function certificate(
        int $id,
        string $recipient,
        string $programme,
        int $organizationId = 1,
        int $catalogTypeId = 5,
        string $achievementDate = '2026-08-02',
    ): ProCertificate
    {
        $certificate = new ProCertificate([
            'organization_id' => $organizationId,
            'catalog_type_id' => $catalogTypeId,
            'certificate_type' => 'professional_master',
            'program_title' => $programme,
            'recipient_name' => $recipient,
            'achievement_date' => $achievementDate,
        ]);
        $certificate->setAttribute('id', $id);

        return $certificate;
    }
}
