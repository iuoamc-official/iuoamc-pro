<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class CertificateVerificationTest extends TestCase
{
    public function test_malformed_reference_returns_neutral_arabic_verification_page(): void
    {
        $response = $this->get('/verify/c/not-a-certificate-reference');

        $response
            ->assertNotFound()
            ->assertSee('<html lang="ar" dir="rtl">', false)
            ->assertSee('التحقق غير متاح')
            ->assertSee('assets/css/iuoamc-verification-2.0.0.css', false)
            ->assertDontSee('<script', false);
    }

    public function test_verification_page_supports_english_and_french_locales(): void
    {
        $this->get('/verify/c/invalid?lang=en')
            ->assertNotFound()
            ->assertSee('<html lang="en" dir="ltr">', false)
            ->assertSee('Verification unavailable');

        $this->get('/verify/c/invalid?lang=fr')
            ->assertNotFound()
            ->assertSee('<html lang="fr" dir="ltr">', false)
            ->assertSee('Vérification indisponible');
    }

    public function test_verification_response_enforces_enterprise_security_headers(): void
    {
        $response = $this->get('/verify/c/invalid?lang=en');

        $response
            ->assertNotFound()
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
            ->assertHeader('Cross-Origin-Resource-Policy', 'same-origin')
            ->assertHeader(
                'Permissions-Policy',
                'camera=(), geolocation=(), microphone=(), payment=(), usb=()'
            );

        $cacheControl = (string) $response->headers->get('Cache-Control');
        $contentSecurityPolicy = (string) $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString("script-src 'none'", $contentSecurityPolicy);
        $this->assertStringContainsString("frame-ancestors 'none'", $contentSecurityPolicy);
        $this->assertStringContainsString("form-action 'none'", $contentSecurityPolicy);
    }

    public function test_malformed_proof_reference_returns_private_json_error(): void
    {
        $response = $this->getJson('/verify/c/invalid/proof?lang=en');

        $response
            ->assertNotFound()
            ->assertExactJson(['message' => 'Verification unavailable'])
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');

        $this->assertStringContainsString(
            'no-store',
            (string) $response->headers->get('Cache-Control')
        );
    }
}
