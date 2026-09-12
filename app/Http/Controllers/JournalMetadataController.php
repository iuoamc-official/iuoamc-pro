<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Journal;
use App\Models\JournalArticle;
use App\Services\JournalScholarlyMetadata;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

final class JournalMetadataController extends Controller
{
    public function citation(string $locale, JournalArticle $article, string $format, JournalScholarlyMetadata $metadata): Response
    {
        $this->assertPublished($article);
        abort_unless($article->journal->setting('citation_exports_enabled', false) === true, 404);
        abort_unless(in_array($format, ['bibtex', 'ris'], true), 404);

        $content = $format === 'bibtex' ? $metadata->bibtex($article, $locale) : $metadata->ris($article, $locale);
        JournalArticle::query()->whereKey($article->id)->increment('citation_downloads_count');

        return response($content, 200, [
            'Content-Type' => $format === 'bibtex' ? 'application/x-bibtex; charset=UTF-8' : 'application/x-research-info-systems; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.Str::slug($article->article_code).'.'.($format === 'bibtex' ? 'bib' : 'ris').'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function jats(string $locale, JournalArticle $article, JournalScholarlyMetadata $metadata): Response
    {
        $this->assertPublished($article);
        abort_unless($article->journal->setting('jats_export_enabled', false) === true, 404);
        JournalArticle::query()->whereKey($article->id)->increment('jats_downloads_count');

        return response($metadata->jats($article, $locale), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.Str::slug($article->article_code).'.xml"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function oai(Request $request, JournalScholarlyMetadata $metadata): Response
    {
        $journal = Journal::query()->where('status', 'active')->firstOrFail();
        abort_unless($journal->isPubliclyLaunched() && $journal->setting('oai_pmh_enabled', false) === true, 404);
        $verb = Str::limit((string) $request->query('verb'), 80, '');
        $identifier = Str::limit((string) $request->query('identifier'), 255, '');
        $metadataPrefix = Str::limit((string) $request->query('metadataPrefix'), 80, '');

        return response($metadata->oai($journal, $verb, $identifier ?: null, $metadataPrefix ?: null), 200, [
            'Content-Type' => 'text/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function assertPublished(JournalArticle $article): void
    {
        abort_unless(in_array($article->status, ['published', 'retracted'], true) && $article->published_at !== null, 404);
    }
}
