<?php
declare(strict_types=1);

namespace App\Services;

use Illuminate\Validation\ValidationException;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use RuntimeException;

/** One versioned A4 background, original brand pixels, immutable V2 issuance data. */
final class ProMasterCertificatePdf
{
    public const PRINT_LAYOUT_REVISION = '1.2.5';
    public const LAYOUT = 'master_a4_v1';
    public const BACKGROUND_SHA256 = '40adc332ef7648f5999f10d75129091d1169ef594cc8217dc8e6f0e4ce682731';
    public const ISSUER_LEGAL_NAME = 'INTERNATIONAL CULINARY & GASTRONOMY ARBITRATION LTD';
    public const LIMITS = [
        'certificate_title' => 120, 'recipient_name' => 180, 'program_title' => 140,
        'specialization' => 100, 'statement' => 280,
        'signatory_name' => 80, 'signatory_title' => 80,
    ];

    /** Static issuer registrations may never appear on another institution's award. */
    public static function supportsIssuer(array $issuer): bool
    {
        return ($issuer['jurisdiction'] ?? null) === 'GB'
            && ($issuer['registration_number'] ?? null) === '16846998'
            && ($issuer['legal_name'] ?? null) === self::ISSUER_LEGAL_NAME;
    }

    public function render(array $payload): string
    {
        $language = $payload['language'] ?? null;
        $catalog = $payload['catalog_snapshot'] ?? null;
        $issuer = $payload['issuer'] ?? null;
        if (!in_array($language, ['ar', 'en', 'fr'], true)
            || ($payload['template_version'] ?? null) !== 'IUOAMC-PRO-CERT-1.1.0'
            || ($payload['schema'] ?? null) !== 'iuoamc-pro-certificate-v2'
            || !is_array($issuer) || !self::supportsIssuer($issuer)
            || !is_array($catalog) || ($catalog['layout'] ?? null) !== self::LAYOUT
            || ($catalog['category'] ?? null) !== 'professional_master'
            || ($payload['certificate_type'] ?? null) !== 'professional_master'
            || !ProCertificateCatalog::validSnapshot($catalog, (int) ($catalog['id'] ?? 0), (int) ($issuer['id'] ?? 0))) {
            throw ValidationException::withMessages(['catalog_type_id' => self::message($language, 'issuer')]);
        }
        $draft = ($payload['draft'] ?? false) === true;
        $url = $payload['verification_url'] ?? '';
        if (!is_string($url) || (!$draft && !preg_match('~\Ahttps://iuoamc\.pro/verify/c/[a-f0-9]{64}\z~D', $url))) {
            throw new RuntimeException('MASTER_CERTIFICATE_VERIFICATION_URL_INVALID');
        }
        foreach (self::LIMITS as $field => $maximum) {
            $value = $payload[$field] ?? ($field === 'specialization' ? '' : null);
            if ($value === null && $field === 'specialization') { $value = ''; }
            if (!is_string($value) || ($field !== 'specialization' && trim($value) === '')
                || preg_match('//u', $value) !== 1 || mb_strlen($value) > $maximum
                || preg_match($field === 'statement' ? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u' : '/[\x00-\x1F\x7F]/u', $value) !== 0
                || ($field === 'statement' && substr_count($value, "\n") > 2)) {
                throw ValidationException::withMessages([$field => self::message($language, 'fit')]);
            }
            $payload[$field] = $value;
        }
        $number = $draft ? 'NOT ISSUED' : ($payload['certificate_number'] ?? null);
        if (!is_string($number) || !preg_match('/\A[A-Z0-9 -]{1,64}\z/D', $number)) {
            throw new RuntimeException('MASTER_CERTIFICATE_NUMBER_INVALID');
        }
        $achievement = $payload['achievement_date'] ?? null;
        $expires = $payload['expires_on'] ?? null;
        $issued = $draft ? null : substr((string) ($payload['issued_at'] ?? ''), 0, 10);
        foreach ([$achievement, $expires, $issued] as $index => $date) {
            if ($date === null && ($index === 1 || ($index === 2 && $draft))) { continue; }
            if (!is_string($date) || !preg_match('/\A\d{4}-\d{2}-\d{2}\z/D', $date)
                || !checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
                throw new RuntimeException('MASTER_CERTIFICATE_DATE_INVALID');
            }
        }
        $background = resource_path('certificates/master-a4-v1.2.4.pdf');
        if (!is_file($background) || is_link($background)
            || !hash_equals(self::BACKGROUND_SHA256, (string) hash_file('sha256', $background))) {
            throw new RuntimeException('MASTER_CERTIFICATE_BACKGROUND_INTEGRITY_FAILED');
        }
        $temporary = $this->temporaryDirectory();
        $mpdf = new Mpdf([
            'mode' => 'utf-8', 'format' => 'A4',
            'margin_left' => 0, 'margin_right' => 0, 'margin_top' => 0, 'margin_bottom' => 0,
            'margin_header' => 0, 'margin_footer' => 0,
            'default_font' => 'dejavusans', 'default_font_size' => 10,
            'tempDir' => $temporary, 'autoScriptToLang' => true, 'autoLangToFont' => true,
        ]);
        $mpdf->SetDirectionality($language === 'ar' ? 'rtl' : 'ltr');
        $mpdf->SetTitle($payload['certificate_title']);
        $mpdf->SetAuthor(self::ISSUER_LEGAL_NAME);
        $mpdf->SetCreator('IUOAMC Pro - master_a4_v1 print revision 1.2.5');
        $mpdf->SetSubject($draft ? 'NOT ISSUED - review draft' : $number);
        if ($mpdf->setSourceFile($background) !== 1) { throw new RuntimeException('MASTER_CERTIFICATE_BACKGROUND_PAGE_COUNT'); }
        $template = $mpdf->importPage(1);
        $mpdf->AddPage();
        $mpdf->useTemplate($template, 0, 0, 210, 297);
        $labels = ProCertificatePdf::labels($language);
        $dir = $language === 'ar' ? 'rtl' : 'ltr';
        // Bounded plain-text measurement at a readable minimum precedes mPDF fit-to-box.
        // The source strings stay unchanged; text is neither clipped nor truncated.
        $this->text($mpdf, $payload['certificate_title'], [24,96,162,27], 23, 15, $dir, true, 'certificate_title', $language);
        $this->text($mpdf, $labels['recipient'], [25,125,160,8], 10, 9, $dir, false, 'recipient_name', $language, '#6E624A');
        $this->text($mpdf, $payload['recipient_name'], [24,136,162,27], 24, 14, $dir, true, 'recipient_name', $language);
        $this->text($mpdf, $payload['program_title'], [25,160,160,10], 13, 9.5, $dir, true, 'program_title', $language);
        if ($payload['specialization'] !== '') {
            $this->text($mpdf, $labels['specialization'].': '.$payload['specialization'], [25,170,160,7], 9, 8, $dir, false, 'specialization', $language, '#8A6825');
        }
        [$outcome, $wicpRegistration] = $this->splitStatement($payload['statement']);
        $recognition = match ($language) {
            'ar' => "استوفى بنجاح متطلبات البرنامج والتقييم المهني\nوتمنح له هذه الشهادة تقديرًا للكفاءة التي أثبتها.",
            'fr' => "A satisfait aux exigences du programme et de l'évaluation professionnelle\net reçoit ce certificat en reconnaissance des compétences démontrées.",
            default => "Has successfully fulfilled the programme and professional assessment requirements\nand is awarded this certificate in recognition of demonstrated competence.",
        };
        $this->text($mpdf, $recognition, [25,180,160,9], 8.4, 7.6, $dir, false, 'statement', $language);
        if ($outcome !== '') {
            $this->text($mpdf, $outcome, [25,191,160,5.2], 8.8, 7.4, $dir, false, 'statement', $language);
        }
        if ($wicpRegistration !== '') {
            $this->text($mpdf, $wicpRegistration, [25,198,160,5.2], 7.8, 6.8, 'ltr', false, 'statement', $language, '#8A6825');
        }
        // Metadata uses independent fixed boxes, not a compressed HTML table.
        // This prevents labels, certificate codes and dates from ever colliding.
        $this->text($mpdf, $labels['number'].':', [28,204,28,3], 6.0, 5.5, $dir, false, 'statement', $language, '#6E624A');
        $this->text($mpdf, $number, [56,204,70,3], 6.5, 5.8, 'ltr', true, 'statement', $language);
        $this->text($mpdf, $labels['type_code'].':', [126,204,20,3], 6.0, 5.5, $dir, false, 'statement', $language, '#6E624A');
        $this->text($mpdf, (string) $catalog['code'], [146,204,36,3], 6.5, 5.8, 'ltr', true, 'statement', $language);
        $this->text($mpdf, $labels['achievement'].':', [28,208,34,3], 6.0, 5.5, $dir, false, 'statement', $language, '#6E624A');
        $this->text($mpdf, $achievement, [62,208,44,3], 6.5, 5.8, 'ltr', true, 'statement', $language);
        $this->text($mpdf, $labels['issued'].':', [106,208,24,3], 6.0, 5.5, $dir, false, 'statement', $language, '#6E624A');
        $this->text($mpdf, $issued ?? '—', [130,208,52,3], 6.5, 5.8, 'ltr', true, 'statement', $language);
        $expiryText = $expires === null ? $labels['no_expiry'] : $labels['expiry'].': '.$expires;
        $this->text($mpdf, $expiryText, [65,212,90,3], 5.5, 5.0, $dir, false, 'statent', $language, '#6E624A');
        if ($draft) {
            $this->text($mpdf, "QR\nNOT ACTIVE", [35,219,22,18], 9, 7, 'ltr', true, 'statement', $language);
            $mpdf->SetWatermarkText('NOT ISSUED', 0.10);
            $mpdf->showWatermarkText = true;
        } else {
            // The only barcode input is the fixed-origin, 256-bit verification token URL.
            $mpdf->WriteFixedPosHTML('<div style="text-align:center;background:#fff"><barcode code="'.self::escape($url).'" type="QR" error="M" size="0.7" disableborder="0" /></div>', 34, 216, 24, 24, 'auto');
        }
        $caption = '<div style="text-align:center;font-size:7pt;color:#102846" dir="ltr">'
            .($draft ? 'iuoamc.pro' : '<a style="color:#102846;text-decoration:none" href="'.self::escape($url).'">iuoamc.pro</a>').'</div>';
        $mpdf->WriteFixedPosHTML($caption, 29, 240, 34, 4.5, 'auto');
        $this->text($mpdf, $payload['signatory_name'], [134,221,52,8], 10, 8.5, $dir, true, 'signatory_name', $language);
        $this->text($mpdf, $payload['signatory_title'], [134,231,52,11], 8.5, 7, $dir, false, 'signatory_title', $language);
        $this->text($mpdf, self::message($language, $draft ? 'draft' : 'signed'), [134,244,52,4], 6.6, 6.2, $dir, false, 'statement', $language, '#8A6825');
        if ($mpdf->page !== 1) { throw ValidationException::withMessages(['statement' => self::message($language, 'fit')]); }
        $bytes = $mpdf->Output('', Destination::STRING_RETURN);
        if (!is_string($bytes) || !str_starts_with($bytes, '%PDF-') || strlen($bytes) < 1024) {
            throw new RuntimeException('MASTER_CERTIFICATE_PDF_RENDER_FAILED');
        }
        return $bytes;
    }

    private function splitStatement(string $statement): array
    {
        $outcome = [];
        $wicp = [];
        foreach (preg_split('/\R/u', trim($statement)) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') { continue; }
            if (preg_match('/\bWICP-[A-Z0-9-]+\b/i', $line)
                || preg_match('/\b(programme|program)\s+registration\b/i', $line)) {
                $wicp[] = $line;
            } else {
                $outcome[] = $line;
            }
        }

        return [implode(' ', $outcome), implode(' ', $wicp)];
    }

    private function text(Mpdf $mpdf, string $text, array $box, float $fontSize, float $minimum, string $direction, bool $bold, string $field, string $language, string $colour = '#102846'): void
    {
        [$x, $y, $width, $height] = $box;
        $mpdf->SetFont('dejavusans', $bold ? 'B' : '', $minimum);
        $lineCount = 0;
        foreach (explode("\n", str_replace("\r", '', $text)) as $paragraph) {
            $line = '';
            $words = preg_split('/\s+/u', trim($paragraph)) ?: [];
            foreach ($words as $word) {
                if ($mpdf->GetStringWidth($word) > $width - 2) {
                    throw ValidationException::withMessages([$field => self::message($language, 'fit')]);
                }
                $next = $line === '' ? $word : $line.' '.$word;
                if ($line !== '' && $mpdf->GetStringWidth($next) > $width - 2) { ++$lineCount; $line = $word; }
                else { $line = $next; }
            }
            ++$lineCount;
        }
        if ($lineCount * $minimum * (25.4 / 72) * 1.17 > $height - 0.2) {
            throw ValidationException::withMessages([$field => self::message($language, 'fit')]);
        }
        $html = '<div dir="'.$direction.'" style="margin:0;padding:0;text-align:center;line-height:1.17;color:'.$colour.';font-family:dejavusans;font-size:'.$fontSize.'pt;font-weight:'.($bold ? 'bold' : 'normal').'">'
            .nl2br(self::escape($text), false).'</div>';
        $mpdf->WriteFixedPosHTML($html, $x, $y, $width, $height, 'auto');
    }

    private function temporaryDirectory(): string
    {
        $directory = realpath(storage_path('app'));
        if ($directory === false) { throw new RuntimeException('MASTER_CERTIFICATE_PDF_TEMP_UNAVAILABLE'); }
        foreach (['private', 'pro-master-pdf-temp'] as $segment) {
            $directory .= '/'.$segment;
            if (is_link($directory) || (!is_dir($directory) && !@mkdir($directory, 0700) && !is_dir($directory))
                || realpath($directory) !== $directory) {
                throw new RuntimeException('MASTER_CERTIFICATE_PDF_TEMP_UNAVAILABLE');
            }
        }
        if ((fileperms($directory) & 0077) !== 0) { throw new RuntimeException('MASTER_CERTIFICATE_PDF_TEMP_NOT_PRIVATE'); }
        return $directory;
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }

    private static function message(mixed $language, string $key): string
    {
        return match ($language) {
            'ar' => match ($key) {
                'issuer' => 'القالب الطولي مخصص لشهادات الماستر المهني الصادرة عن ICGA UK المسجلة برقم 16846998.',
                'fit' => 'النص أطول من المساحة المقروءة في القالب الطولي. اختصر هذا الحقل ثم أعد المعاينة؛ لا يتم اقتطاع البيانات.',
                'draft' => 'مسودة للمراجعة · لم تُصدر',
                default => 'سجل إصدار موقّع رقميًا · تحقّق عبر الرمز',
            },
            'fr' => match ($key) {
                'issuer' => 'Ce modèle portrait est réservé aux certificats de master professionnel émis par ICGA UK, société 16846998.',
                'fit' => 'Le texte dépasse la zone lisible du modèle portrait. Raccourcissez ce champ puis relancez l’aperçu. Aucune donnée n’est tronquée.',
                'draft' => 'BROUILLON · NON DÉLIVRÉ',
                default => 'Registre signé numériquement · Vérifier le QR',
            },
            default => match ($key) {
                'issuer' => 'This portrait layout is reserved for professional master certificates issued by ICGA UK, company 16846998.',
                'fit' => 'The text exceeds the readable portrait area. Shorten this field and preview again; certificate data is never truncated.',
                'draft' => 'REVIEW DRAFT · NOT ISSUED',
                default => 'Digitally signed record · Verify the QR',
            },
        };
    }
}
