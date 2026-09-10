<?php
declare(strict_types=1);

namespace App\Services;

use Illuminate\Validation\ValidationException;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use RuntimeException;

final class ProCertificatePdf
{
    public const TEMPLATE_VERSION = 'IUOAMC-PRO-CERT-1.0.0';

    /** Render an immutable issuance snapshot, never a database model or user HTML. */
    public function render(array $payload): string
    {
        $language = $payload['language'] ?? '';
        $template = $payload['template_version'] ?? '';
        if (!in_array($language, ['ar', 'en', 'fr'], true)
            || !in_array($template, [self::TEMPLATE_VERSION, 'IUOAMC-PRO-CERT-1.1.0'], true)) {
            throw new RuntimeException('CERTIFICATE_TEMPLATE_UNSUPPORTED');
        }
        $isCatalog = $template === 'IUOAMC-PRO-CERT-1.1.0';
        if ($isCatalog && (!is_array($payload['catalog_snapshot'] ?? null)
            || !in_array($payload['catalog_snapshot']['layout'] ?? '', ['classic', 'diploma', 'master_a4_v1'], true))) {
            throw new RuntimeException('CERTIFICATE_CATALOG_TEMPLATE_UNSUPPORTED');
        }
        // New immutable catalog layout only; historical templates retain their original renderer.
        if ($isCatalog && $payload['catalog_snapshot']['layout'] === 'master_a4_v1') {
            return app(ProMasterCertificatePdf::class)->render($payload);
        }
        $draft = ($payload['draft'] ?? false) === true;
        $url = (string) ($payload['verification_url'] ?? '');
        if (!$draft && !preg_match('~\Ahttps://iuoamc\.pro/verify/c/[a-f0-9]{64}\z~D', $url)) {
            throw new RuntimeException('CERTIFICATE_VERIFICATION_URL_INVALID');
        }
        $logo = public_path('assets/brand/iuoamc-pro-logo.png');
        if (!is_file($logo) || is_link($logo)) {
            throw new RuntimeException('CERTIFICATE_BRAND_ASSET_MISSING');
        }
        $authorityLogo = null;
        $arbitrationLogo = null;
        if ($isCatalog && $payload['catalog_snapshot']['layout'] === 'diploma') {
            $arbitrationLogo = public_path('assets/brand/master-v1/icga-original.jpg');
            $authorityLogo = public_path('assets/brand/master-v1/wsaca-authority-seal-transparent.svg');
            if (!is_file($arbitrationLogo) || is_link($arbitrationLogo)) {
                throw new RuntimeException('CERTIFICATE_ARBITRATION_ASSET_MISSING');
            }
            if (!is_file($authorityLogo) || is_link($authorityLogo)) {
                throw new RuntimeException('CERTIFICATE_AUTHORITY_ASSET_MISSING');
            }
            $authorityAsset = file_get_contents($authorityLogo);
            if (!is_string($authorityAsset)
                || !preg_match('~data:image/png;base64,([A-Za-z0-9+/=]+)~', $authorityAsset, $authorityMatch)) {
                throw new RuntimeException('CERTIFICATE_AUTHORITY_ASSET_INVALID');
            }
            // mPDF does not consistently paint embedded raster images inside SVG files.
            // Supplying the same transparent PNG payload directly is deterministic.
            $authorityLogo = 'data:image/png;base64,'.$authorityMatch[1];
        }
        $temporary = realpath(storage_path('app'));
        if ($temporary === false) {
            throw new RuntimeException('CERTIFICATE_PDF_TEMP_UNAVAILABLE');
        }
        // Check only descendants of Laravel storage, compatible with Hestia open_basedir.
        foreach (['private', 'pro-certificates', 'pdf-temp'] as $segment) {
            $temporary .= '/'.$segment;
            if (is_link($temporary)
                || (!is_dir($temporary) && !@mkdir($temporary, 0700) && !is_dir($temporary))
                || realpath($temporary) !== $temporary) {
                throw new RuntimeException('CERTIFICATE_PDF_TEMP_UNAVAILABLE');
            }
        }
        if ((fileperms($temporary) & 0077) !== 0) {
            throw new RuntimeException('CERTIFICATE_PDF_TEMP_NOT_PRIVATE');
        }
        $labels = self::labels($language);
        $diplomaLayout = $isCatalog && $payload['catalog_snapshot']['layout'] === 'diploma';
        $mpdf = new Mpdf([
            'mode' => 'utf-8', 'format' => 'A4-L',
            'margin_left' => $diplomaLayout ? 7 : 15,
            'margin_right' => $diplomaLayout ? 7 : 15,
            'margin_top' => $diplomaLayout ? 7 : 12,
            'margin_bottom' => $diplomaLayout ? 7 : 13,
            'margin_header' => 0, 'margin_footer' => 5,
            'default_font' => 'dejavusans', 'default_font_size' => 11,
            'tempDir' => $temporary,
            'autoScriptToLang' => true, 'autoLangToFont' => true,
        ]);
        if ($diplomaLayout) {
            // This layout is dimensioned in millimetres for one physical A4 sheet.
            // Prevent mPDF from creating a second page for fixed security overlays.
            $mpdf->SetAutoPageBreak(false, 0);
        }
        $mpdf->SetDirectionality($language === 'ar' ? 'rtl' : 'ltr');
        $mpdf->SetTitle((string) ($payload['certificate_title'] ?? $labels['document']));
        $mpdf->SetAuthor((string) ($payload['issuer']['legal_name'] ?? 'IUOAMC'));
        $mpdf->SetCreator('IUOAMC Pro - '.$template);
        $mpdf->SetSubject((string) ($payload['certificate_number'] ?? 'DRAFT'));
        if ($draft) {
            $mpdf->SetWatermarkText('DRAFT', 0.10);
            $mpdf->showWatermarkText = true;
        }
        // Only the fixed, escaped Blade document is rendered. No remote assets/user HTML.
        $view = $diplomaLayout
            ? 'pro_certificates.diploma_a4'
            : ($isCatalog ? 'pro_certificates.document_v2' : 'pro_certificates.document');
        $mpdf->WriteHTML(view($view, compact('payload', 'language', 'labels', 'logo', 'authorityLogo', 'arbitrationLogo', 'draft'))->render());
        if ($mpdf->page !== 1) {
            throw ValidationException::withMessages(['statement' => $labels['too_long']]);
        }
        $bytes = $mpdf->Output('', Destination::STRING_RETURN);
        if (!is_string($bytes) || !str_starts_with($bytes, '%PDF-') || strlen($bytes) < 1024) {
            throw new RuntimeException('CERTIFICATE_PDF_RENDER_FAILED');
        }
        return $bytes;
    }

    public static function labels(string $language): array
    {
        return match ($language) {
            'ar' => [
                'document' => 'شهادة مؤسسية', 'recipient' => 'تُمنح هذه الشهادة إلى',
                'specialization' => 'التخصص', 'type_code' => 'كود النوع',
                'professional_credential' => 'اعتماد مهني', 'certified_programme' => 'البرنامج المهني المعتمد',
                'authenticity_verification' => 'التحقق من الأصالة', 'secure_qr' => 'امسح رمز QR الآمن أو زُر iuoamc.pro',
                'program_ip_code' => 'رمز سجل الملكية الفكرية للبرنامج',
                'program' => 'البرنامج / المناسبة', 'achievement' => 'تاريخ الإنجاز',
                'issued' => 'تاريخ الإصدار', 'expiry' => 'صالحة حتى', 'no_expiry' => 'دون تاريخ انتهاء محدد',
                'number' => 'رقم الشهادة', 'registration' => 'رقم تسجيل الجهة',
                'verify' => 'امسح الرمز للتحقق من الحالة الحالية',
                'footer' => 'يرتبط هذا المستند بسجل إصدار موقّع رقميًا. راجع الحالة الحالية عبر رابط التحقق.',
                'draft' => 'مسودة للمراجعة - لم تُصدر هذه الشهادة',
                'too_long' => 'النص يتجاوز مساحة قالب الشهادة. اختصر العنوان أو الاسم أو النص ثم افتح المعاينة مجددًا.',
            ],
            'fr' => [
                'document' => 'CERTIFICAT INSTITUTIONNEL', 'recipient' => 'Ce certificat est décerné à',
                'specialization' => 'Spécialité', 'type_code' => 'Code du type',
                'professional_credential' => 'TITRE PROFESSIONNEL', 'certified_programme' => 'PROGRAMME PROFESSIONNEL CERTIFIÉ',
                'authenticity_verification' => 'VÉRIFICATION D’AUTHENTICITÉ', 'secure_qr' => 'Scannez le QR sécurisé ou consultez iuoamc.pro',
                'program_ip_code' => 'CODE DU REGISTRE DE PROPRIÉTÉ INTELLECTUELLE DU PROGRAMME',
                'program' => 'Programme / Événement', 'achievement' => 'Date de réalisation',
                'issued' => 'Date de délivrance', 'expiry' => 'Valable jusqu’au', 'no_expiry' => 'Sans date d’expiration définie',
                'number' => 'Numéro du certificat', 'registration' => 'Immatriculation de l’émetteur',
                'verify' => 'Scannez pour vérifier le statut actuel',
                'footer' => 'Ce document est lié à un registre signé numériquement. Consultez le lien pour son statut actuel.',
                'draft' => 'BROUILLON POUR RÉVISION - CERTIFICAT NON DÉLIVRÉ',
                'too_long' => 'Le texte dépasse le format du certificat. Raccourcissez le titre, le nom ou le texte, puis ouvrez à nouveau l’aperçu.',
            ],
            default => [
                'document' => 'INSTITUTIONAL CERTIFICATE', 'recipient' => 'This certificate is awarded to',
                'specialization' => 'Specialisation', 'type_code' => 'Type code',
                'professional_credential' => 'PROFESSIONAL CREDENTIAL', 'certified_programme' => 'CERTIFIED PROFESSIONAL PROGRAMME',
                'authenticity_verification' => 'AUTHENTICITY VERIFICATION', 'secure_qr' => 'Scan the secure QR code or visit iuoamc.pro',
                'program_ip_code' => 'PROGRAM INTELLECTUAL PROPERTY REGISTRY CODE',
                'program' => 'Programme / Event', 'achievement' => 'Achievement date',
                'issued' => 'Issued on', 'expiry' => 'Valid until', 'no_expiry' => 'No expiry date specified',
                'number' => 'Certificate number', 'registration' => 'Issuer registration',
                'verify' => 'Scan to check the current status',
                'footer' => 'This document is linked to a digitally signed issuance record. Check the verification link for its current status.',
                'draft' => 'REVIEW DRAFT - THIS CERTIFICATE HAS NOT BEEN ISSUED',
                'too_long' => 'The text exceeds this certificate format. Shorten the title, name or statement, then preview again.',
            ],
        };
    }
}
