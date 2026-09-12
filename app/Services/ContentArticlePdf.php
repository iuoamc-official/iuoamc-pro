<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ContentArticle;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use RuntimeException;

final class ContentArticlePdf
{
    private const BODY_MARKER = '__IUOAMC_PDF_ARTICLE_BODY__';

    private const HTML_CHUNK_BYTES = 150000;

    public function __construct(private readonly ArticleBodyFormatter $formatter) {}

    /** @param array<string, mixed> $siteProfile */
    public function render(ContentArticle $article, string $locale, array $siteProfile): string
    {
        $temporary = $this->temporaryDirectory();
        $body = $this->formatter->toHtml($article->localized('body', $locale));
        $watermark = $this->watermarkLogoPath();
        $cover = $this->temporaryCoverPng($article, $temporary);
        $direction = $locale === 'ar' ? 'rtl' : 'ltr';

        try {
            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_left' => 18,
                'margin_right' => 18,
                'margin_top' => 17,
                'margin_bottom' => 18,
                'default_font' => 'dejavusans',
                'default_font_size' => 11,
                'tempDir' => $temporary,
                'autoScriptToLang' => true,
                'autoLangToFont' => true,
            ]);
            $mpdf->SetDirectionality($direction);
            $mpdf->SetTitle($article->localized('title', $locale));
            $mpdf->SetAuthor($article->author_name);
            $mpdf->SetCreator('IUOAMC Editorial Publishing System');
            $mpdf->SetSubject($article->section?->localized('name', $locale) ?? 'IUOAMC Editorial Article');
            $mpdf->SetFooter('{PAGENO} / {nbpg}');
            $mpdf->SetWatermarkImage($watermark, 0.06, [150, 61], 'P');
            $mpdf->showWatermarkImage = true;

            $document = view('articles.pdf', [
                ...compact('article', 'locale', 'siteProfile', 'cover', 'direction'),
                'body' => self::BODY_MARKER,
            ])->render();
            $parts = explode(self::BODY_MARKER, $document, 2);
            if (count($parts) !== 2) {
                throw new RuntimeException('CONTENT_ARTICLE_PDF_TEMPLATE_INVALID');
            }

            $this->writeHtmlChunks($mpdf, $parts[0]);
            $this->writeHtmlChunks($mpdf, $body);
            $this->writeHtmlChunks($mpdf, $parts[1]);
            $bytes = $mpdf->Output('', Destination::STRING_RETURN);
        } finally {
            if ($cover !== null && is_file($cover)) {
                @unlink($cover);
            }
        }

        if (! is_string($bytes) || ! str_starts_with($bytes, '%PDF-') || strlen($bytes) < 1024) {
            throw new RuntimeException('CONTENT_ARTICLE_PDF_RENDER_FAILED');
        }

        return $bytes;
    }

    private function watermarkLogoPath(): string
    {
        $logo = public_path('assets/brand/iuoamc-pro-logo.png');
        $resolved = realpath($logo);
        $brandDirectory = realpath(public_path('assets/brand'));

        if ($resolved === false
            || $brandDirectory === false
            || is_link($logo)
            || ! str_starts_with($resolved, $brandDirectory.DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('CONTENT_ARTICLE_PDF_WATERMARK_MISSING');
        }

        return $resolved;
    }

    private function temporaryDirectory(): string
    {
        $base = realpath(storage_path('app'));
        if ($base === false) {
            throw new RuntimeException('CONTENT_ARTICLE_PDF_TEMP_UNAVAILABLE');
        }

        $temporary = $base.'/private/article-pdf-temp';
        foreach ([$base.'/private', $temporary] as $directory) {
            if (is_link($directory)
                || (! is_dir($directory) && ! @mkdir($directory, 0700) && ! is_dir($directory))
                || realpath($directory) !== $directory) {
                throw new RuntimeException('CONTENT_ARTICLE_PDF_TEMP_UNAVAILABLE');
            }
        }
        if ((fileperms($temporary) & 0077) !== 0) {
            throw new RuntimeException('CONTENT_ARTICLE_PDF_TEMP_NOT_PRIVATE');
        }

        return $temporary;
    }

    private function temporaryCoverPng(ContentArticle $article, string $temporary): ?string
    {
        if (! $article->cover_image_path) {
            return null;
        }

        $disk = \Illuminate\Support\Facades\Storage::disk('public');
        if (! $disk->exists($article->cover_image_path)) {
            return null;
        }

        $source = $disk->get($article->cover_image_path);
        if ($source === '' || strlen($source) > 12 * 1024 * 1024 || ! function_exists('imagecreatefromstring')) {
            return null;
        }

        $image = @imagecreatefromstring($source);
        if ($image === false) {
            return null;
        }

        $path = tempnam($temporary, 'cover-');
        if ($path === false) {
            return null;
        }

        if (! imagepng($image, $path, 7) || ! is_file($path) || filesize($path) === 0) {
            @unlink($path);

            return null;
        }

        return $path;
    }

    private function writeHtmlChunks(Mpdf $mpdf, string $html): void
    {
        if ($html === '') {
            return;
        }

        $blocks = preg_split('/\n(?=<)/u', $html) ?: [$html];
        $chunk = '';

        foreach ($blocks as $block) {
            if ($chunk !== '' && strlen($chunk) + strlen($block) + 1 > self::HTML_CHUNK_BYTES) {
                $mpdf->WriteHTML($chunk);
                $chunk = '';
            }

            if ($chunk !== '') {
                $chunk .= "\n";
            }
            $chunk .= $block;
        }

        if ($chunk !== '') {
            $mpdf->WriteHTML($chunk);
        }
    }
}
