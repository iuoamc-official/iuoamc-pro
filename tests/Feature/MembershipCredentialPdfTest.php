<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\MembershipCredentialPdf;
use App\Services\MembershipRegistry;
use Tests\TestCase;

class MembershipCredentialPdfTest extends TestCase
{
    public function test_premium_membership_card_renders_front_and_back_and_certificate_renders_as_valid_pdf(): void
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
            'nationality_code' => 'GB',
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
        $this->assertStringContainsString('<div class="brand"><img', $template);
        $this->assertStringContainsString('NATIONALITY', $template);
        $this->assertStringContainsString('VALID FROM', $template);
        $this->assertStringContainsString('VALID UNTIL', $template);
        $this->assertStringContainsString('NFC ENABLED', $template);
        $this->assertStringContainsString('<pagebreak />', $template);
        $this->assertStringContainsString('OFFICIAL MEMBERSHIP CREDENTIAL', $template);
        $this->assertStringContainsString('This card remains the property of', $template);
        $this->assertStringContainsString('TAP WITH A COMPATIBLE DEVICE', $template);
        $this->assertStringContainsString('info@iuoamc.uk', $template);
        $this->assertFileExists(public_path('assets/brand/nfc-contactless-gold.svg'));
        $this->assertStringContainsString("payload['nationality_code']", $template);
        $this->assertStringNotContainsString('gradient', $template);
        $this->assertStringNotContainsString('opacity:', $template);
        $this->assertStringNotContainsString('filter:', $template);
        $this->assertStringNotContainsString('box-shadow', $template);
        $this->assertStringNotContainsString('object-fit', $template);
    }

    public function test_print_archive_supports_web_safe_imagick_without_proc_open(): void
    {
        $service = file_get_contents(app_path('Services/MembershipCredentialPrintArchive.php'));

        $this->assertIsString($service);
        $this->assertStringContainsString('class_exists(\\Imagick::class)', $service);
        $this->assertStringContainsString("function_exists('proc_open')", $service);
        $this->assertStringContainsString('getNumberImages() !== count($outputPaths)', $service);
        $this->assertStringContainsString('setResolution(300, 300)', $service);
        $this->assertStringContainsString('createCertificateImage', $service);
        $this->assertStringContainsString('MEMBERSHIP_CERTIFICATE_IMAGE_DIMENSIONS_INVALID', $service);
        $this->assertStringContainsString('LAYERMETHOD_FLATTEN', file_get_contents(
            app_path('Services/MembershipCredentialRegistry.php')
        ));
        $this->assertStringContainsString('cropThumbnailImage(900, 1200)', file_get_contents(
            app_path('Services/MembershipCredentialRegistry.php')
        ));
    }

    public function test_membership_portraits_stay_inside_identity_frames(): void
    {
        $template = file_get_contents(resource_path('views/membership_credentials/certificate.blade.php'));

        $this->assertIsString($template);
        $this->assertStringContainsString('class="identity-photo-frame"', $template);
        $this->assertStringContainsString('top: 65mm; left: 47mm', $template);
        $this->assertStringContainsString('overflow: hidden', $template);
        $this->assertStringContainsString('background: #fbfaf6', $template);
        $this->assertStringNotContainsString('class="photo-frame"', $template);
        $this->assertStringContainsString('border-radius: 50%', $template);
        $this->assertStringContainsString('class="nfc-seal"', $template);
        $this->assertStringContainsString("format('d-m-Y')", $template);
        $this->assertSame('IUOAMC-MEMBERSHIP-2.2.2', MembershipCredentialPdf::TEMPLATE_VERSION);
        $this->assertContains('IUOAMC-MEMBERSHIP-2.2.0', MembershipCredentialPdf::PRINT_IMAGE_TEMPLATES);
        $this->assertContains('IUOAMC-MEMBERSHIP-2.2.1', MembershipCredentialPdf::PRINT_IMAGE_TEMPLATES);

        $card = file_get_contents(resource_path('views/membership_credentials/card.blade.php'));
        $this->assertIsString($card);
        $this->assertStringContainsString('width: 16.6mm; height: 20.6mm', $card);
        $this->assertStringContainsString('overflow: hidden', $card);
        $this->assertStringContainsString('padding: 0', $card);
        $this->assertStringContainsString('border-radius: 50%', $card);
        $this->assertStringContainsString("format('d-m-Y')", $card);

        $registry = file_get_contents(app_path('Services/MembershipCredentialRegistry.php'));
        $this->assertIsString($registry);
        $this->assertStringContainsString("setImageBackgroundColor('#fbfaf6')", $registry);
        $this->assertStringContainsString('imagecolorallocate($target, 251, 250, 246)', $registry);
    }
    public function test_unsigned_preview_flow_does_not_issue_a_credential(): void
    {
        $routes = file_get_contents(base_path('routes/modules/memberships.php'));
        $controller = file_get_contents(app_path('Http/Controllers/Control/MembershipController.php'));
        $registry = file_get_contents(app_path('Services/MembershipCredentialRegistry.php'));
        $card = file_get_contents(resource_path('views/membership_credentials/card.blade.php'));
        $certificate = file_get_contents(resource_path('views/membership_credentials/certificate.blade.php'));

        $this->assertStringContainsString("name('credentials.preview')", $routes);
        $this->assertStringContainsString('public function previewCredentials', $controller);
        $this->assertStringContainsString('public function preview(', $registry);
        $this->assertStringContainsString("renderCard($payload, $photoPath, true)", $registry);
        $this->assertStringContainsString('DRAFT PREVIEW · NOT VALID FOR USE', $card);
        $this->assertStringContainsString('DRAFT PREVIEW · NOT VALID FOR USE', $certificate);
    }

}
