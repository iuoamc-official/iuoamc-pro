<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ContentArticle;
use App\Models\ContentSection;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class ImportLegacyArticles extends Command
{
    protected $signature = 'content:import-legacy-articles
        {--publish : Publish the imported records using their original dates}
        {--confirm= : Required phrase when --publish is used}';

    protected $description = 'Import six curated IUOAMC articles from the legacy publication as reviewable drafts';

    /** @var array<int, array{id:int,slug:string,section:string,featured:bool}> */
    private const ARTICLES = [
        ['id' => 324, 'slug' => 'ahmad-issam-maadarani-professional-profile', 'section' => 'chef-success-stories', 'featured' => true],
        ['id' => 309, 'slug' => 'protecting-intellectual-identity-and-institutional-name', 'section' => 'opinions', 'featured' => false],
        ['id' => 251, 'slug' => 'kitchen-leadership-chef-as-team-builder', 'section' => 'restaurant-insights', 'featured' => true],
        ['id' => 248, 'slug' => 'scientific-structure-of-spices-and-aromatic-herbs', 'section' => 'recipes-techniques', 'featured' => true],
        ['id' => 168, 'slug' => 'cooking-as-a-knowledge-and-ethical-system', 'section' => 'culinary-heritage', 'featured' => false],
        ['id' => 68, 'slug' => 'professionalism-in-culinary-arts', 'section' => 'training-career', 'featured' => false],
    ];

    public function handle(): int
    {
        $publish = (bool) $this->option('publish');

        if ($publish && ! hash_equals('PUBLISH-6-LEGACY-ARTICLES', (string) $this->option('confirm'))) {
            $this->error('PUBLISH_CONFIRMATION_REQUIRED');

            return self::INVALID;
        }

        try {
            $prepared = collect(self::ARTICLES)->map(fn (array $definition): array => $this->prepare($definition))->all();
            $actorId = DB::table('users')->orderBy('id')->value('id');
            $created = 0;
            $skipped = 0;

            DB::transaction(function () use ($prepared, $actorId, $publish, &$created, &$skipped): void {
                foreach ($prepared as $item) {
                    if (ContentArticle::query()->where('slug', $item['definition']['slug'])->exists()) {
                        $skipped++;
                        continue;
                    }

                    $sectionId = ContentSection::query()->where('slug', $item['definition']['section'])->value('id');
                    if ($sectionId === null) {
                        throw new RuntimeException('Missing content section: '.$item['definition']['section']);
                    }

                    $coverPath = $this->storeCover(
                        $item['definition']['id'],
                        $item['cover_extension'],
                        $item['cover_contents'],
                    );
                    $originalDate = Carbon::parse($item['published_at'])->utc();

                    ContentArticle::query()->create([
                        'record_uuid' => (string) Str::uuid(),
                        'content_section_id' => $sectionId,
                        'slug' => $item['definition']['slug'],
                        'title' => $item['title'],
                        'excerpt' => $item['excerpt'],
                        'body' => $item['body'],
                        'seo_title' => $item['title'],
                        'seo_description' => $item['excerpt'],
                        'image_alt' => $item['title'],
                        'author_biography' => $this->authorBiography(),
                        'tags' => $this->tags($item['definition']['section']),
                        'author_name' => 'Ahmad Maadarani',
                        'publisher_name' => 'Ahmad Maadarani',
                        'cover_image_path' => $coverPath,
                        'source_url' => $item['source_url'],
                        'original_published_at' => $originalDate,
                        'status' => $publish ? 'published' : 'draft',
                        'is_featured' => $item['definition']['featured'],
                        'reading_minutes' => max(1, (int) ceil(str_word_count($item['body']['en']) / 220)),
                        'revision' => 1,
                        'published_at' => $publish ? $originalDate : null,
                        'created_by' => $actorId,
                        'updated_by' => $actorId,
                    ]);
                    $created++;
                }
            }, 5);

            $this->components->info($publish ? 'Legacy articles published' : 'Legacy article drafts prepared');
            $this->line('CREATED='.$created);
            $this->line('SKIPPED_EXISTING='.$skipped);
            $this->line('STATUS='.($publish ? 'PUBLISHED' : 'DRAFT'));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('LEGACY_ARTICLE_IMPORT_FAILED');
            $this->line($exception->getMessage());

            return self::FAILURE;
        }
    }

    /** @param array{id:int,slug:string,section:string,featured:bool} $definition */
    private function prepare(array $definition): array
    {
        $localized = [];

        foreach (['ar', 'en', 'fr'] as $locale) {
            $url = "https://www.iuoamc.uk/{$locale}/blog/post/{$definition['id']}";
            $response = $this->client()->get($url)->throw();
            $localized[$locale] = $this->parse((string) $response->body(), $url);
        }

        $cover = $this->downloadCover($localized['ar']['cover_url']);

        return [
            'definition' => $definition,
            'title' => collect($localized)->map(fn (array $page): string => $page['title'])->all(),
            'excerpt' => collect($localized)->map(fn (array $page): string => $page['excerpt'])->all(),
            'body' => collect($localized)->map(fn (array $page): string => $page['body'])->all(),
            'cover_extension' => $cover['extension'],
            'cover_contents' => $cover['contents'],
            'published_at' => $localized['ar']['published_at'],
            'source_url' => $localized['ar']['canonical_url'],
        ];
    }

    private function client(): PendingRequest
    {
        return Http::accept('text/html')->withUserAgent('IUOAMC controlled legacy content migration/1.0')->timeout(30)->retry(3, 400);
    }

    /** @return array{title:string,excerpt:string,body:string,cover_url:string,published_at:string,canonical_url:string} */
    private function parse(string $html, string $fallbackUrl): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            throw new RuntimeException('Unable to parse legacy article: '.$fallbackUrl);
        }

        $xpath = new DOMXPath($document);
        $title = $this->firstText($xpath, '//h1');
        $excerpt = $this->meta($xpath, 'name', 'description');
        $publishedAt = $this->meta($xpath, 'property', 'article:published_time');
        $coverUrl = $this->meta($xpath, 'property', 'og:image');
        $canonical = $this->link($xpath, 'canonical') ?: $this->meta($xpath, 'property', 'og:url') ?: $fallbackUrl;
        $bodyNode = $xpath->query("//div[contains(concat(' ', normalize-space(@class), ' '), ' prose ') and contains(concat(' ', normalize-space(@class), ' '), ' max-w-none ')]")->item(0);
        $body = $bodyNode instanceof DOMElement ? $this->plainText($bodyNode) : '';

        if ($title === '' || $excerpt === '' || $body === '' || $coverUrl === '' || $publishedAt === '') {
            throw new RuntimeException('Legacy article is missing required publication data: '.$fallbackUrl);
        }

        return compact('title', 'excerpt', 'body') + [
            'cover_url' => $coverUrl,
            'published_at' => $publishedAt,
            'canonical_url' => $canonical,
        ];
    }

    private function firstText(DOMXPath $xpath, string $query): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) $xpath->query($query)->item(0)?->textContent) ?? '');
    }

    private function meta(DOMXPath $xpath, string $attribute, string $value): string
    {
        $node = $xpath->query("//meta[@{$attribute}='{$value}']/@content")->item(0);

        return trim((string) $node?->nodeValue);
    }

    private function link(DOMXPath $xpath, string $relation): string
    {
        return trim((string) $xpath->query("//link[@rel='{$relation}']/@href")->item(0)?->nodeValue);
    }

    private function plainText(DOMElement $root): string
    {
        $parts = [];
        $walk = function (DOMNode $node) use (&$walk, &$parts): void {
            if ($node->nodeType === XML_TEXT_NODE) {
                $parts[] = (string) $node->nodeValue;
                return;
            }

            if (! $node instanceof DOMElement) {
                foreach ($node->childNodes as $child) {
                    $walk($child);
                }

                return;
            }

            $tag = strtolower($node->tagName);
            if ($tag === 'br') {
                $parts[] = "\n";
                return;
            }
            if ($tag === 'li') {
                $parts[] = "\n• ";
            }
            if (in_array($tag, ['h1', 'h2', 'h3', 'h4', 'p', 'blockquote'], true)) {
                $parts[] = "\n\n";
            }
            foreach ($node->childNodes as $child) {
                $walk($child);
            }
            if (in_array($tag, ['h1', 'h2', 'h3', 'h4', 'p', 'blockquote', 'li'], true)) {
                $parts[] = "\n";
            }
        };
        $walk($root);

        $text = html_entity_decode(implode('', $parts), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/ *\n */u', "\n", $text) ?? $text;

        return trim(preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text);
    }

    /** @return array{extension:string,contents:string} */
    private function downloadCover(string $url): array
    {
        $response = $this->client()->accept('image/*')->get($url)->throw();
        $contentType = strtolower((string) $response->header('Content-Type'));
        $extension = match (true) {
            str_contains($contentType, 'png') => 'png',
            str_contains($contentType, 'jpeg'), str_contains($contentType, 'jpg') => 'jpg',
            default => 'webp',
        };

        return ['extension' => $extension, 'contents' => $response->body()];
    }

    private function storeCover(int $articleId, string $extension, string $contents): string
    {
        $path = "editorial/articles/legacy/{$articleId}.{$extension}";

        if (! Storage::disk('public')->put($path, $contents)) {
            throw new RuntimeException('Unable to store legacy cover image: '.$articleId);
        }

        return $path;
    }

    /** @return array{ar:string,en:string,fr:string} */
    private function authorBiography(): array
    {
        return [
            'ar' => 'المهندس وماستر شيف أحمد المعدراني، مؤسس ورئيس عام الاتحاد الدولي لماستر شيف العرب، وكاتب وباحث في فنون الطهي والتذوق والتحكيم المهني.',
            'en' => 'Engineer and Master Chef Ahmad Maadarani, founder and President General of IUOAMC, and an author and researcher in culinary arts, tasting and professional judging.',
            'fr' => 'Ingénieur et Master Chef Ahmad Maadarani, fondateur et président général de l’IUOAMC, auteur et chercheur en arts culinaires, dégustation et jugement professionnel.',
        ];
    }

    /** @return array{ar:array<int,string>,en:array<int,string>,fr:array<int,string>} */
    private function tags(string $section): array
    {
        return [
            'ar' => ['الاتحاد الدولي ماستر شيف العرب', 'أحمد المعدراني', $section],
            'en' => ['IUOAMC', 'Ahmad Maadarani', $section],
            'fr' => ['IUOAMC', 'Ahmad Maadarani', $section],
        ];
    }
}
