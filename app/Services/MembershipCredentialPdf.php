<?php

declare(strict_types=1);

namespace App\Services;

use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use RuntimeException;

final class MembershipCredentialPdf
{
    public const TEMPLATE_VERSION = 'IUOAMC-MEMBERSHIP-1.0.0';

    public function renderCard(array $payload, string $photoPath): string
    {
        return $this->render($payload, $photoPath, 'membership_credentials.card', [85.6, 54], 0);
    }

    public function renderCertificate(array $payload, string $photoPath): string
    {
        return $this->render($payload, $photoPath, 'membership_credentials.certificate', 'A4-L', 8);
    }

    private function render(array $payload, string $photoPath, string $view, string|array $format, int $margin): string
    {
        $this->validate($payload, $photoPath);
        $logo = public_path('assets/brand/iuoamc-pro-logo.png');
        if (! is_file($logo) || is_link($logo)) {
            throw new RuntimeException('MEMBERSHIP_BRAND_ASSET_MISSING');
        }

        $temporary = storage_path('app/private/memberships/pdf-temp');
        if (! is_dir($temporary) && ! mkdir($temporary, 0700, true) && ! is_dir($temporary)) {
            throw new RuntimeException('MEMBERSHIP_PDF_TEMP_UNAVAILABLE');
        }
        chmod($temporary, 0700);

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => $format,
            'margin_left' => $margin,
            'margin_right' => $margin,
            'margin_top' => $margin,
            'margin_bottom' => $margin,
            'margin_header' => 0,
            'margin_footer' => 0,
            'default_font' => 'dejavusans',
            'tempDir' => $temporary,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
        ]);
        $mpdf->SetAutoPageBreak(false, 0);
        $mpdf->SetTitle($view === 'membership_credentials.card' ? 'Membership Card' : 'Membership Certificate');
        $mpdf->SetAuthor((string) data_get($payload, 'organization.legal_name', 'IUOAMC'));
        $mpdf->SetCreator('IUOAMC Pro - '.self::TEMPLATE_VERSION);
        $mpdf->SetSubject((string) $payload['membership_number']);
        $mpdf->WriteHTML(view($view, compact('payload', 'photoPath', 'logo'))->render());

        if ($mpdf->page !== 1) {
            throw new RuntimeException('MEMBERSHIP_CREDENTIAL_PAGE_OVERFLOW');
        }
        $bytes = $mpdf->Output('', Destination::STRING_RETURN);
        if (! is_string($bytes) || ! str_starts_with($bytes, '%PDF-') || strlen($bytes) < 1024) {
            throw new RuntimeException('MEMBERSHIP_CREDENTIAL_PDF_INVALID');
        }

        return $bytes;
    }

    private function validate(array $payload, string $photoPath): void
    {
        foreach ([
            'membership_number', 'full_name', 'membership_type', 'valid_from',
            'valid_until', 'verification_url', 'issued_at',
        ] as $required) {
            if (! is_string($payload[$required] ?? null) || trim($payload[$required]) === '') {
                throw new RuntimeException('MEMBERSHIP_CREDENTIAL_PAYLOAD_INVALID');
            }
        }
        if (($payload['schema'] ?? null) !== 'iuoamc-membership-credential-v1'
            || ($payload['template_version'] ?? null) !== self::TEMPLATE_VERSION
            || ! preg_match('~\Ahttps://iuoamc\.pro/verify/m/[a-f0-9]{64}\z~D', $payload['verification_url'])
            || ! is_file($photoPath)
            || is_link($photoPath)) {
            throw new RuntimeException('MEMBERSHIP_CREDENTIAL_PAYLOAD_INVALID');
        }
    }
}
