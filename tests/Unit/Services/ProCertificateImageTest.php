<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\ProCertificateImage;
use RuntimeException;
use Tests\TestCase;

final class ProCertificateImageTest extends TestCase
{
    public function test_rejects_an_unsupported_download_variant(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('CERTIFICATE_IMAGE_VARIANT_UNSUPPORTED');

        app(ProCertificateImage::class)->render('/missing.pdf', 'unknown');
    }

    public function test_rejects_a_missing_source_pdf(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('CERTIFICATE_IMAGE_SOURCE_UNAVAILABLE');

        app(ProCertificateImage::class)->render('/missing.pdf', 'print');
    }

    public function test_renders_print_and_sharing_images_from_a_certificate_pdf(): void
    {
        if (! class_exists(\Imagick::class)
            && ! is_executable('/usr/bin/pdftocairo')
            && ! is_executable('/usr/local/bin/pdftocairo')) {
            self::markTestSkipped('A supported PDF image converter is unavailable.');
        }

        $source = resource_path('certificates/master-a4-v1.3.1.pdf');
        $service = app(ProCertificateImage::class);

        try {
            $print = $service->render($source, 'print');
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() === 'CERTIFICATE_IMAGE_CONVERTER_UNAVAILABLE') {
                self::markTestSkipped('The installed Imagick build cannot read PDF and Poppler is unavailable.');
            }

            throw $exception;
        }

        $share = $service->render($source, 'share');

        self::assertSame('image/png', $print['mime']);
        self::assertSame('png', $print['extension']);
        self::assertStringStartsWith("\x89PNG\r\n\x1a\n", $print['bytes']);
        self::assertGreaterThan(3000, max($print['width'], $print['height']));
        self::assertGreaterThan(2000, min($print['width'], $print['height']));

        self::assertSame('image/jpeg', $share['mime']);
        self::assertSame('jpg', $share['extension']);
        self::assertStringStartsWith("\xff\xd8\xff", $share['bytes']);
        self::assertLessThanOrEqual(2000, max($share['width'], $share['height']));
        self::assertGreaterThan(1000, min($share['width'], $share['height']));
    }
}
