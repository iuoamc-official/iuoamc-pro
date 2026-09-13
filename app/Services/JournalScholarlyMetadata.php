<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Journal;
use App\Models\JournalArticle;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use JsonException;

final class JournalScholarlyMetadata
{
    private const OAI_PAGE_SIZE = 25;

    public function bibtex(JournalArticle $article, string $locale): string
    {
        $article->loadMissing(['journal', 'issue', 'authors', 'translations']);
        $translation = $article->translation($locale);
        $key = Str::slug(($article->authors->first()?->latin_name ?: 'MCIJ').'-'.($article->published_at?->year ?: 'record'), '');
        $fields = [
            'title' => $translation?->title,
            'author' => $article->authors->map(fn ($author): string => $author->latin_name ?: $author->name)->implode(' and '),
            'journal' => $article->journal->localized('name', $locale),
            'year' => $article->published_at?->year,
            'volume' => $article->issue?->volume,
            'number' => $article->issue?->number,
            'pages' => $article->page_start ? $article->page_start.($article->page_end ? '--'.$article->page_end : '') : null,
            'doi' => $article->doi,
            'url' => route('journal.public.articles.show', ['locale' => $locale, 'article' => $article->slug]),
        ];

        $lines = collect($fields)->filter(fn ($value): bool => filled($value))->map(
            fn ($value, string $field): string => '  '.$field.' = {'.$this->bibtexValue((string) $value).'}'
        )->implode(",\n");

        return "@article{{$key},\n{$lines}\n}\n";
    }

    public function ris(JournalArticle $article, string $locale): string
    {
        $article->loadMissing(['journal', 'issue', 'authors', 'translations']);
        $translation = $article->translation($locale);
        $lines = ['TY  - JOUR'];
        foreach ($article->authors as $author) {
            $lines[] = 'AU  - '.($author->latin_name ?: $author->name);
        }
        $lines[] = 'TI  - '.$translation?->title;
        $lines[] = 'JO  - '.$article->journal->localized('name', $locale);
        $lines[] = 'PY  - '.$article->published_at?->year;
        if ($article->issue) {
            $lines[] = 'VL  - '.$article->issue->volume;
            $lines[] = 'IS  - '.$article->issue->number;
        }
        if ($article->page_start) {
            $lines[] = 'SP  - '.$article->page_start;
        }
        if ($article->page_end) {
            $lines[] = 'EP  - '.$article->page_end;
        }
        if ($article->doi) {
            $lines[] = 'DO  - '.$article->doi;
        }
        foreach ($translation?->keywords ?? [] as $keyword) {
            $lines[] = 'KW  - '.$keyword;
        }
        $lines[] = 'UR  - '.route('journal.public.articles.show', ['locale' => $locale, 'article' => $article->slug]);
        $lines[] = 'ER  - ';

        return implode("\r\n", $lines)."\r\n";
    }

    public function jats(JournalArticle $article, string $locale): string
    {
        $article->loadMissing(['journal', 'issue', 'authors', 'translations', 'sections']);
        $translation = $article->translation($locale);
        $articleType = $article->type === 'peer_reviewed_research' ? 'research-article' : 'other';
        $authors = $article->authors->map(function ($author): string {
            $name = $author->latin_name ?: $author->name;
            $orcid = $author->orcid ? '<contrib-id contrib-id-type="orcid">https://orcid.org/'.$this->xml($author->orcid).'</contrib-id>' : '';

            return '<contrib contrib-type="author"><name><surname>'.$this->xml($name).'</surname></name>'.$orcid.'</contrib>';
        })->implode('');
        $keywords = collect($translation?->keywords ?? [])->map(fn ($keyword): string => '<kwd>'.$this->xml((string) $keyword).'</kwd>')->implode('');
        $references = collect($translation?->references ?? [])->values()->map(
            fn ($reference, int $index): string => '<ref id="ref'.($index + 1).'"><mixed-citation>'.$this->xml((string) $reference).'</mixed-citation></ref>'
        )->implode('');
        $paragraphs = preg_split('/\n{2,}/u', trim((string) $translation?->body)) ?: [];
        $body = collect($paragraphs)->filter()->map(fn ($paragraph): string => '<p>'.$this->xml(trim($paragraph)).'</p>')->implode('');
        $date = $article->published_at;
        $doi = $article->doi ? '<article-id pub-id-type="doi">'.$this->xml($article->doi).'</article-id>' : '';

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<!DOCTYPE article PUBLIC "-//NLM//DTD JATS (Z39.96) Journal Archiving and Interchange DTD v1.3 20210610//EN" "JATS-archivearticle1-3.dtd">'."\n"
            .'<article xmlns:xlink="http://www.w3.org/1999/xlink" article-type="'.$articleType.'" xml:lang="'.$this->xml($locale).'">'
            .'<front><journal-meta><journal-id journal-id-type="publisher-id">'.$this->xml($article->journal->code).'</journal-id>'
            .'<journal-title-group><journal-title>'.$this->xml($article->journal->localized('name', $locale)).'</journal-title></journal-title-group>'
            .($article->journal->issn ? '<issn pub-type="ppub">'.$this->xml($article->journal->issn).'</issn>' : '')
            .($article->journal->eissn ? '<issn pub-type="epub">'.$this->xml($article->journal->eissn).'</issn>' : '')
            .'<publisher><publisher-name>'.$this->xml($article->journal->publisher_name).'</publisher-name></publisher></journal-meta>'
            .'<article-meta><article-id pub-id-type="publisher-id">'.$this->xml($article->article_code).'</article-id>'.$doi
            .'<title-group><article-title>'.$this->xml((string) $translation?->title).'</article-title></title-group>'
            .'<contrib-group>'.$authors.'</contrib-group>'
            .($date ? '<pub-date publication-format="electronic"><day>'.$date->format('d').'</day><month>'.$date->format('m').'</month><year>'.$date->format('Y').'</year></pub-date>' : '')
            .($article->issue ? '<volume>'.$article->issue->volume.'</volume><issue>'.$article->issue->number.'</issue>' : '')
            .($article->page_start ? '<fpage>'.$article->page_start.'</fpage>' : '').($article->page_end ? '<lpage>'.$article->page_end.'</lpage>' : '')
            .'<permissions><copyright-statement>'.$this->xml($article->license).'</copyright-statement></permissions>'
            .'<abstract><p>'.$this->xml((string) $translation?->abstract).'</p></abstract>'
            .($keywords !== '' ? '<kwd-group>'.$keywords.'</kwd-group>' : '').'</article-meta></front>'
            .'<body>'.$body.'</body>'.($references !== '' ? '<back><ref-list>'.$references.'</ref-list></back>' : '').'</article>';
    }

    /** @param array<string, string> $arguments */
    public function oai(Journal $journal, array $arguments, string $locale = 'en'): string
    {
        $baseUrl = route('journal.oai');
        $responseDate = now()->utc()->format('Y-m-d\TH:i:s\Z');
        $verb = $arguments['verb'] ?? '';
        $identifier = $arguments['identifier'] ?? null;
        $metadataPrefix = $arguments['metadataPrefix'] ?? null;
        $metadataVerbs = ['GetRecord', 'ListIdentifiers', 'ListRecords'];
        $content = match (true) {
            isset($arguments['resumptionToken']) && array_diff(array_keys($arguments), ['verb', 'resumptionToken']) !== [] => '<error code="badArgument">A resumptionToken must be the only argument besides the verb.</error>',
            in_array($verb, $metadataVerbs, true) && ! isset($arguments['resumptionToken']) && $metadataPrefix !== 'oai_dc' => '<error code="cannotDisseminateFormat">Only oai_dc metadata is available.</error>',
            $verb === 'GetRecord' && blank($identifier) => '<error code="badArgument">The identifier argument is required.</error>',
            $verb === 'Identify' => $this->oaiIdentify($journal, $baseUrl),
            $verb === 'ListMetadataFormats' => '<ListMetadataFormats><metadataFormat><metadataPrefix>oai_dc</metadataPrefix><schema>http://www.openarchives.org/OAI/2.0/oai_dc.xsd</schema><metadataNamespace>http://www.openarchives.org/OAI/2.0/oai_dc/</metadataNamespace></metadataFormat></ListMetadataFormats>',
            $verb === 'ListSets' => $this->oaiSets($journal, $locale),
            $verb === 'GetRecord' => $this->oaiGetRecord($journal, $identifier, $locale),
            $verb === 'ListIdentifiers' => $this->oaiList($journal, $locale, false, $arguments),
            $verb === 'ListRecords' => $this->oaiList($journal, $locale, true, $arguments),
            default => '<error code="badVerb">The verb argument is missing or illegal.</error>',
        };

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/ http://www.openarchives.org/OAI/2.0/OAI-PMH.xsd">'
            .'<responseDate>'.$responseDate.'</responseDate>'.$this->oaiRequest($baseUrl, $arguments).$content.'</OAI-PMH>';
    }

    private function oaiIdentify(Journal $journal, string $baseUrl): string
    {
        $earliest = $journal->articles()->published()->oldest('published_at')->value('published_at') ?: now();

        return '<Identify><repositoryName>'.$this->xml($journal->localized('name', 'en')).'</repositoryName><baseURL>'.$this->xml($baseUrl).'</baseURL>'
            .'<protocolVersion>2.0</protocolVersion><adminEmail>'.$this->xml((string) $journal->setting('contact_email', 'info@iuoamc.uk')).'</adminEmail>'
            .'<earliestDatestamp>'.$this->xml((string) date('Y-m-d\TH:i:s\Z', strtotime((string) $earliest))).'</earliestDatestamp>'
            .'<deletedRecord>persistent</deletedRecord><granularity>YYYY-MM-DDThh:mm:ssZ</granularity></Identify>';
    }

    private function oaiSets(Journal $journal, string $locale): string
    {
        $sets = $journal->sections()->active()->orderBy('sort_order')->get()->map(
            fn ($section): string => '<set><setSpec>'.$this->xml($section->slug).'</setSpec><setName>'.$this->xml($section->localized('name', $locale)).'</setName></set>'
        )->implode('');

        return $sets === '' ? '<error code="noSetHierarchy">This repository has no sets.</error>' : '<ListSets>'.$sets.'</ListSets>';
    }

    private function oaiGetRecord(Journal $journal, ?string $identifier, string $locale): string
    {
        $code = Str::after((string) $identifier, 'oai:iuoamc.pro:');
        $article = $journal->articles()->published()->where('article_code', $code)->with(['translations', 'authors', 'sections'])->first();

        return $article
            ? '<GetRecord>'.$this->oaiRecord($article, $locale, true).'</GetRecord>'
            : '<error code="idDoesNotExist">The identifier does not match a published record.</error>';
    }

    /** @param array<string, string> $arguments */
    private function oaiList(Journal $journal, string $locale, bool $includeMetadata, array $arguments): string
    {
        $state = ['after' => 0, 'from' => $arguments['from'] ?? null, 'until' => $arguments['until'] ?? null, 'set' => $arguments['set'] ?? null];
        if (isset($arguments['resumptionToken'])) {
            try {
                $decoded = json_decode(Crypt::decryptString($arguments['resumptionToken']), true, 512, JSON_THROW_ON_ERROR);
            } catch (DecryptException|JsonException) {
                return '<error code="badResumptionToken">The resumption token is invalid or expired.</error>';
            }
            if (! is_array($decoded) || ($decoded['verb'] ?? null) !== ($includeMetadata ? 'ListRecords' : 'ListIdentifiers') || (int) ($decoded['expires'] ?? 0) < now()->timestamp) {
                return '<error code="badResumptionToken">The resumption token is invalid or expired.</error>';
            }
            $state = array_merge($state, array_intersect_key($decoded, $state));
        }

        $from = $this->oaiDate($state['from'], false);
        $until = $this->oaiDate($state['until'], true);
        if (($state['from'] !== null && $from === null) || ($state['until'] !== null && $until === null) || ($from !== null && $until !== null && $from > $until)) {
            return '<error code="badArgument">The from and until arguments must be valid UTC dates in chronological order.</error>';
        }

        $query = $journal->articles()->published()
            ->with(['translations', 'authors', 'sections'])
            ->when($from, fn (Builder $query, DateTimeImmutable $date): Builder => $query->where('published_at', '>=', $date->format('Y-m-d H:i:s')))
            ->when($until, fn (Builder $query, DateTimeImmutable $date): Builder => $query->where('published_at', '<=', $date->format('Y-m-d H:i:s')))
            ->when($state['set'], fn (Builder $query, string $set): Builder => $query->whereHas('sections', fn (Builder $section): Builder => $section->where('slug', $set)->where('status', 'active')))
            ->where('journal_articles.id', '>', (int) $state['after'])
            ->orderBy('journal_articles.id');
        $articles = $query->limit(self::OAI_PAGE_SIZE + 1)->get();
        if ($articles->isEmpty()) {
            return '<error code="noRecordsMatch">No published records match the request.</error>';
        }
        $hasMore = $articles->count() > self::OAI_PAGE_SIZE;
        $page = $articles->take(self::OAI_PAGE_SIZE);
        $records = $page->map(fn (JournalArticle $article): string => $this->oaiRecord($article, $locale, $includeMetadata))->implode('');
        $token = '';
        if ($hasMore) {
            $next = array_merge($state, [
                'verb' => $includeMetadata ? 'ListRecords' : 'ListIdentifiers',
                'after' => $page->last()->id,
                'expires' => now()->addDay()->timestamp,
            ]);
            $token = '<resumptionToken expirationDate="'.now()->addDay()->utc()->format('Y-m-d\TH:i:s\Z').'">'.$this->xml(Crypt::encryptString((string) json_encode($next, JSON_THROW_ON_ERROR))).'</resumptionToken>';
        }

        return '<'.($includeMetadata ? 'ListRecords' : 'ListIdentifiers').'>'.$records.$token.'</'.($includeMetadata ? 'ListRecords' : 'ListIdentifiers').'>';
    }

    /** @param array<string, string> $arguments */
    private function oaiRequest(string $baseUrl, array $arguments): string
    {
        $attributes = collect($arguments)->map(
            fn (string $value, string $key): string => ' '.$this->xml($key).'="'.$this->xml($value).'"'
        )->implode('');

        return '<request'.$attributes.'>'.$this->xml($baseUrl).'</request>';
    }

    private function oaiDate(mixed $value, bool $endOfDay): ?DateTimeImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }
        $timezone = new DateTimeZone('UTC');
        $format = preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? '!Y-m-d' : '!Y-m-d\TH:i:s\Z';
        $date = DateTimeImmutable::createFromFormat($format, $value, $timezone);
        if ($date === false || $date->format(ltrim($format, '!')) !== $value) {
            return null;
        }

        return $endOfDay && $format === '!Y-m-d' ? $date->setTime(23, 59, 59) : $date;
    }

    private function oaiRecord(JournalArticle $article, string $locale, bool $includeMetadata): string
    {
        $translation = $article->translation($locale);
        $sets = $article->sections->map(fn ($section): string => '<setSpec>'.$this->xml($section->slug).'</setSpec>')->implode('');
        $header = '<header'.($article->status === 'retracted' ? ' status="deleted"' : '').'><identifier>oai:iuoamc.pro:'.$this->xml($article->article_code).'</identifier>'
            .'<datestamp>'.$article->published_at?->utc()->format('Y-m-d\TH:i:s\Z').'</datestamp>'.$sets.'</header>';
        if (! $includeMetadata || $article->status === 'retracted') {
            return $includeMetadata ? '<record>'.$header.'</record>' : $header;
        }
        $authors = $article->authors->map(fn ($author): string => '<dc:creator>'.$this->xml($author->latin_name ?: $author->name).'</dc:creator>')->implode('');
        $subjects = collect($translation?->keywords ?? [])->map(fn ($keyword): string => '<dc:subject>'.$this->xml((string) $keyword).'</dc:subject>')->implode('');
        $metadata = '<metadata><oai_dc:dc xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/oai_dc/ http://www.openarchives.org/OAI/2.0/oai_dc.xsd">'
            .'<dc:title>'.$this->xml((string) $translation?->title).'</dc:title>'.$authors.$subjects
            .'<dc:description>'.$this->xml((string) $translation?->abstract).'</dc:description><dc:publisher>'.$this->xml($article->journal->publisher_name).'</dc:publisher>'
            .'<dc:date>'.$article->published_at?->format('Y-m-d').'</dc:date><dc:type>'.$this->xml($article->type).'</dc:type><dc:language>'.$this->xml($locale).'</dc:language>'
            .'<dc:identifier>'.$this->xml(route('journal.public.articles.show', ['locale' => $locale, 'article' => $article->slug])).'</dc:identifier>'
            .($article->doi ? '<dc:identifier>https://doi.org/'.$this->xml($article->doi).'</dc:identifier>' : '').'<dc:rights>'.$this->xml($article->license).'</dc:rights></oai_dc:dc></metadata>';

        return '<record>'.$header.$metadata.'</record>';
    }

    private function bibtexValue(string $value): string
    {
        return str_replace(['\\', '{', '}', "\r", "\n"], ['\\\\', '\\{', '\\}', ' ', ' '], $value);
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
