<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\PdrtseCertificateDefinition;
use PHPUnit\Framework\TestCase;

final class PdrtseCertificateDefinitionTest extends TestCase
{
    public function test_it_pins_the_controlled_type_identity_and_exact_english_wording(): void
    {
        $definition = new PdrtseCertificateDefinition;
        $profile = $definition->typeProfile(91);

        self::assertSame('PDRTSE', $profile['code']);
        self::assertSame('ICGA-PDRTSE', $profile['number_prefix']);
        self::assertSame(91, $profile['organization_id']);
        self::assertSame(PdrtseCertificateDefinition::TITLE_EN, $profile['title_en']);
        self::assertSame(
            PdrtseCertificateDefinition::STATEMENT_EN."\n".PdrtseCertificateDefinition::DISCLAIMER_EN,
            $profile['statement_en'],
        );
        self::assertSame(PdrtseCertificateDefinition::DESIGNATION, 'Certified Restaurant Tasting and Sensory Evaluation Specialist');
        self::assertSame('President General & Authorised Signatory', $profile['signatory_title']);
    }

    public function test_private_recipient_identity_is_not_part_of_the_public_type_profile(): void
    {
        $profile = (new PdrtseCertificateDefinition)->typeProfile(91);

        self::assertArrayNotHasKey('recipient_name', $profile);
        self::assertArrayNotHasKey('recipient_email', $profile);
        self::assertStringNotContainsString('@', json_encode($profile, JSON_THROW_ON_ERROR));
    }
}
