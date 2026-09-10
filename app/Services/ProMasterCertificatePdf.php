<?php
declare(strict_types=1);

namespace App\Services;

use Illuminate\Validation\ValidationException;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use RuntimeException;

/** One versioned enterprise A4 background, original brand pixels, immutable V2 issuance data. */
final class ProMasterCertificatePdf
{
    public const PRINT_LAYOUT_REVISION = '1.3.1';
    public const LAYOUT = 'master_a4_v1';
    public const BACKGROUND_SHA256 = '53bba9e3f27b1f80c782449602d027af807ab1e28232c8b6f363f304e9cf51be';
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
        $background = resource_path('certificates/master-a4-v1.3.1.pdf');
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
        $mpdf->SetCreator('IUOAMC Pro - master_a4_v1 print revision '.self::PRINT_LAYOUT_REVISION);
        $mpdf->SetSubject($draft ? 'NOT ISSUED - review draft' : $number);
        if ($mpdf->setSourceFile($background) !== 1) { throw new RuntimeException('MASTER_CERTIFICATE_BACKGROUND_PAGE_COUNT'); }
        $template = $mpdf->importPage(1);
        $mpdf->AddPage();
        $mpdf->useTemplate($template, 0, 0, 210, 297);
        $labels = ProCertificatePdf::labels($language);
        $dir = $language === 'ar' ? 'rtl' : 'ltr';
        // Bounded plain-text measurement at a readable minimum precedes mPDF fit-to-box.
        // The source strings stay unchanged; text is neither clipped nor truncated.
        $this->text($mpdf, self::message($language, $draft ? 'draft_band' : 'issued_band'), [16,62.2,178,4], 6.4, 5.8, $dir, true, 'statement', $language, '#B88A2A');
        $this->text($mpdf, $labels['professional_credential'], [25,73,160,5], 7.2, 6.5, $dir, true, 'certificate_title', $language, '#0C675A');
        $this->text($mpdf, $payload['certificate_title'], [20,81,170,16], 23, 15, $dir, true, 'certificate_title', $language);
        $this->text($mpdf, $labels['recipient'], [25,100,160,6], 8, 7.2, $dir, false, 'recipient_name', $language, '#607080');
        $this->text($mpdf, $payload['recipient_name'], [24,108,162,27], 19, 13, $dir, true, 'recipient_name', $language);
        $this->text($mpdf, $labels['certified_programme'], [28,148,154,5], 6.6, 6.0, $dir, true, 'program_title', $language, '#0C675A');
        $this->text($mpdf, $payload['program_title'], [27,155,156,11], 11.5, 8.6, $dir, true, 'program_title', $language);
        if ($payload['specialization'] !== '') {
            $this->text($mpdf, $labels['specialization'].': '.$payload['specialization'], [27,165,156,5], 7.4, 6.6, $dir, false, 'specialization', $language, '#8A6825');
        }
        [$outcome, $wicpRegistration] = $this->splitStatement($payload['statement']);
        $ipCode = self::programIpCodeFromStatement($wicpRegistration);
        if ($ipCode === null) {
            throw ValidationException::withMessages(['statement' => self::message($language, 'ip_required')]);
        }
        $recognition = match ($language) {
            'ar' => "استوفى بنجاح متطلبات البرنامج والتقييم المهني\nوتمنح له هذه الشهادة تقديرًا للكفاءة التي أثبتها.",
            'fr' => "A satisfait aux exigences du programme et de l'évaluation professionnelle\net reçoit ce certificat en reconnaissance des compétences démontrées.",
            default => "Has successfully fulfilled the programme and professional assessment requirements\nand is awarded this certificate in recognition of demonstrated competence.",
        };
        $this->text($mpdf, $recognition, [25,171,160,9], 7.5, 6.8, $dir, false, 'statement', $language, '#607080');
        if ($outcome !== '') {
            $this->text($mpdf, $outcome, [25,182,160,5.2], 8.8, 7.2, $dir, true, 'statement', $language);
        }
        // Metadata uses independent fixed boxes, not a compressed HTML table.
        // This prevents labels, certificate codes and dates from ever colliding.
        $this->metadata($mpdf, $labels['number'], $number, [24,190.3,75,12], $dir, $language);
        $this->metadata($mpdf, $labels['type_code'], (string) $catalog['code'], [111,190.3,75,12], $dir, $language);
        $this->metadata($mpdf, $labels['achievement'], $achievement, [24,208.5,75,12], $dir, $language);
        $this->metadata($mpdf, $labels['issued'], $issued ?? '-', [111,208.5,75,12], $dir, $language);
        if ($draft) {
            $this->text($mpdf, "QR\nNOT ACTIVE", [22,234,26,24], 8.5, 7, 'ltr', true, 'statement', $language);
            $mpdf->SetWatermarkText('NOT ISSUED', 0.10);
            $mpdf->showWatermarkText = true;
        } else {
            // The only barcode input is the fixed-origin, 256-bit verification token URL.
            $mpdf->WriteFixedPosHTML('<div style="text-align:center;background:#fff"><barcode code="'.self::escape($url).'" type="QR" error="M" size="0.7" disableborder="0" /></div>', 22, 233.5, 26, 26, 'auto');
        }
        $this->text($mpdf, $labels['authenticity_verification'], [51,237.5,71,5], 6.8, 6.1, $dir, true, 'statement', $language, '#0C675A');
        $this->text($mpdf, $labels['secure_qr'], [51,244,71,5], 6.1, 5.5, $dir, false, 'statement', $language, '#607080');
        $expiryText = $expires === null ? $labels['no_expiry'] : $labels['expiry'].': '.$expires;
        $this->text($mpdf, self::message($language, $draft ? 'draft' : 'signed').' - '.$expiryText, [51,249,71,5], 5.8, 5.1, $dir, false, 'statement', $language, '#607080');
        $this->text($mpdf, $labels['program_ip_code'], [51,255.5,71,4], 5.8, 5.2, $dir, true, 'statement', $language);
        $this->text($mpdf, $ipCode, [51,260,71,4], 5.2, 4.8, 'ltr', false, 'statement', $language);
        $this->text($mpdf, $payload['signatory_name'], [130,228.5,49,7], 8.8, 7.6, $dir, true, 'signatory_name', $language);
        $this->text($mpdf, $payload['signatory_title'], [130,234.7,49,7], 6.2, 5.4, $dir, false, 'signatory_title', $language, '#607080');
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

    public static function programIpCodeFromStatement(mixed $statement): ?string
    {
        return is_string($statement)
            && preg_match('/\b(WICP-PRO-P-[0-9]{4}-[a-f0-9]{32})\b/i', $statement, $matches) === 1
            ? $matches[1]
            : null;
    }

    private function metadata(Mpdf $mpdf, string $label, string $value, array $box, string $direction, string $language): void
    {
        [$x, $y, $width, $height] = $box;
        $this->text($mpdf, mb_strtoupper($label), [$x, $y, $width, 3.5], 6.1, 5.5, $direction, true, 'statement', $language, '#0C675A');
        $this->text($mpdf, $value, [$x, $y + 5, $width, $height - 5], 8.7, 6.8, 'ltr', true, 'statement', $language);
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
                'draft_band' => 'نسخة مراجعة | نظام الشهادات المؤسسي V3 | لم تصدر بعد',
                'issued_band' => 'سجل مهني محمي | نظام الشهادات المؤسسي V3 | إصدار موثق',
                'ip_required' => 'يجب أن يتضمن بيان شهادة الماستر رمز تسجيل ملكية فكرية للبرنامج بصيغة WICP-PRO-P-YYYY- ثم 32 خانة سداسية.',
                default => 'سجل إصدار موقّع رقميًا · تحقّق عبر الرمز',
            },
            'fr' => match ($key) {
                'issuer' => 'Ce modèle portrait est réservé aux certificats de master professionnel émis par ICGA UK, société 16846998.',
                'fit' => 'Le texte dépasse la zone lisible du modèle portrait. Raccourcissez ce champ puis relancez l’aperçu. Aucune donnée n’est tronquée.',
                'draft' => 'BROUILLON · NON DÉLIVRÉ',
                'draft_band' => 'ÉPREUVE | SYSTÈME DE CERTIFICATS ENTREPRISE V3 | NON DÉLIVRÉ',
                'issued_band' => 'REGISTRE PROFESSIONNEL PROTÉGÉ | SYSTÈME ENTREPRISE V3 | DÉLIVRÉ',
                'ip_required' => 'La déclaration doit contenir le code de propriété intellectuelle du programme au format WICP-PRO-P-AAAA suivi de 32 caractères hexadécimaux.',
                default => 'Registre signé numériquement · Vérifier le QR',
            },
            default => match ($key) {
                'issuer' => 'This portrait layout is reserved for professional master certificates issued by ICGA UK, company 16846998.',
                'fit' => 'The text exceeds the readable portrait area. Shorten this field and preview again; certificate data is never truncated.',
                'draft' => 'REVIEW DRAFT · NOT ISSUED',
                'draft_band' => 'DESIGN PROOF | ENTERPRISE CERTIFICATE SYSTEM V3 | NOT YET ISSUED',
                'issued_band' => 'PROTECTED PROFESSIONAL RECORD | ENTERPRISE SYSTEM V3 | VERIFIED ISSUE',
                'ip_required' => 'The master certificate statement must contain a programme intellectual-property code in the format WICP-PRO-P-YYYY- followed by 32 hexadecimal characters.',
                default => 'Digitally signed record · Verify the QR',
            },
        };
    }
}
