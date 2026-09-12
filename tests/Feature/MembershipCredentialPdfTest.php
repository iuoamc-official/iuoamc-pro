<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\MembershipCredentialPdf;
use App\Services\MembershipRegistry;
use Tests\TestCase;

class MembershipCredentialPdfTest extends TestCase
{
    public function test_premium_membership_card_and_certificate_render_as_single_valid_pdf_pages(): void
    {
        $photo = tempnam(sys_get_temp_dir(), 'iuoamc-member-photo-');
        $this->assertIsString($photo);
        file_put_contents($photo, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true
        ));

        $payload = [
            'schema' => 'iuoamc-membership-credential-v1',
            'membership_id' => 42,
            'record_uuid' => '20c01121-98c0-4997-a152-92869109e066',
            'membership_number' => 'IUOAMC-MEM-2026-000042',
            'period_uuid' => '87ea34a4-0048-446d-98c1-12ba831be232',
            'version' => 2,
            'full_name' => 'عضو تجريبي طويل الاسم',
            'latin_name' => 'Example Member With A Long Professional Name',
            'membership_type' => 'Professional Membership',
            'professional_title' => 'Executive Chef',
            'country_code' => 'GB',
            'organization' => [
                'id' => 1,
                'code' => 'IUOAMC',
                'legal_name' => 'INTERNATIONAL UNION OF ARAB MASTER CHEFS LTD',
                'display_name' => 'IUOAMC',
                'jurisdiction' => 'GB',
                'registration_number' => '16649793',
            ],
            'valid_from' => '2026-09-12',
            'valid_until' => '2028-09-11',
            'photo_sha256' => hash_file('sha256', $photo),
            'verification_url' => 'https://iuoamc.pro/verify/m/'.str_repeat('a', 64),
            'issued_by' => 1,
            'issued_at' => '2026-09-12T12:00:00+00:00',
            'template_version' => MembershipCredentialPdf::TEMPLATE_VERSION,
            'electronic_signature' => [
                'name' => 'Master Chef Ahmad Maadarani',
                'title' => 'President General & Authorised Signatory',
                'standard' => 'PAdES/X.509',
            ],
        ];
        $payload['credential_data_sha256'] = MembershipRegistry::digest($payload);

        try {
            $renderer = app(MembershipCredentialPdf::class);
            $card = $renderer->renderCard($payload, $photo);
            $certificate = $renderer->renderCertificate($payload, $photo);

            $this->assertStringStartsWith('%PDF-', $card);
            $this->assertStringStartsWith('%PDF-', $certificate);
            $this->assertGreaterThan(1024, strlen($card));
            $this->assertGreaterThan(1024, strlen($certificate));
        } finally {
            @unlink($photo);
        }
    }

    public function test_pvc_card_uses_print_safe_fixed_layout_without_unsupported_effects(): void
    {
        $template = file_get_contents(resource_path('views/membership_credentials/card.blade.php'));

        $this->assertIsString($template);
        $this->assertStringContainsString('85.6mm', $template);
        $this->assertStringContainsString('54mm', $template);
        $this->assertStringContainsString('PVC ID-1', $template);
        $this->assertStringContainsString('position: fixed', $template);
        $this->assertStringContainsString('#172e57', $template);
        $this->assertStringContainsString('#c6a13c', $template);
        $this->assertStringNotContainsString('gradient', $template);
        $this->assertStringNotContainsString('opacity:', $template);
        $this->assertStringNotContainsString('filter:', $template);
        $this->assertStringNotContainsString('box-shadow', $template);
        $this->assertStringNotContainsString('object-fit', $template);
    }
}
