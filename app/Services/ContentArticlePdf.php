<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ContentArticle;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use RuntimeException;

final class ContentArticlePdf
{
    public function __construct(private readonly ArticleBodyFormatter $formatter) {}

    /** @param array<string, mixed> $siteProfile */
    public function render(ContentArticle $article, string $locale, array $siteProfile): string
    {
        $temporary = $this->temporaryDirectory();
        $cover = $this->coverDataUri($article);
        $body = $this->formatter->toHtml($article->localized('body', $locale));
        $direction = $locale === 'ar' ? 'rtl' : 'ltr';

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
        $mpdf->WriteHTML(view('articles.pdf', compact('article', 'locale', 'siteProfile', 'cover', 'body', 'direction'))->render());
        $bytes = $mpdf->Output('', Destination::STRING_RETURN);

        if (! is_string($bytes) || ! str_starts_with($bytes, '%PDF-') || strlen($bytes) < 1024) {
            throw new RuntimeException('CONTENT_ARTICLE_PDF_RENDER_FAILED');
        }

        return $bytes;
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

    private function coverDataUri(ContentArticle $article): ?string
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

        ob_start();
        $rendered = imagepng($image, null, 7);
        $png = ob_get_clean();
        imagedestroy($image);

        if (! $rendered || ! is_string($png) || $png === '') {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($png);
    }
}
