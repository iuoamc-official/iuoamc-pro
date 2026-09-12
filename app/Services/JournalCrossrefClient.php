<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\JournalArticle;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

final class JournalCrossrefClient
{
    /** @return array{deposit_id: string, response: string} */
    public function deposit(JournalArticle $article, bool $test = false): array
    {
        $article->loadMissing(['journal', 'issue', 'authors', 'translations']);
        $this->assertReady($article);
        $depositId = 'MCIJ-'.$article->article_code.'-V'.$article->version_of_record;
        $xml = $this->xml($article, $depositId);
        $response = $this->request($test)
            ->attach('fname', $xml, 'mcij-crossref-'.$article->id.'.xml')
            ->post('', [
                'operation' => 'doMDUpload',
                'login_id' => (string) config('services.crossref.username'),
                'login_passwd' => (string) config('services.crossref.password'),
            ])
            ->throw();

        if (! $test) {
            $article->update([
                'crossref_deposited_at' => now()->utc()->startOfSecond(),
                'crossref_deposit_id' => $depositId,
            ]);
        }

        return ['deposit_id' => $depositId, 'response' => $response->body()];
    }

    private function request(bool $test): PendingRequest
    {
        return Http::baseUrl((string) config($test ? 'services.crossref.test_endpoint' : 'services.crossref.endpoint'))
            ->connectTimeout(5)
            ->timeout(30)
            ->retry([500, 1500], throw: false)
            ->accept('text/plain');
    }

    private function assertReady(JournalArticle $article): void
    {
        $missing = collect([
            'username' => config('services.crossref.username'),
            'password' => config('services.crossref.password'),
            'depositor_name' => config('services.crossref.depositor_name'),
            'depositor_email' => config('services.crossref.depositor_email'),
        ])->filter(fn ($value): bool => blank($value))->keys()->all();

        if ($missing !== []) {
            throw ValidationException::withMessages(['crossref' => 'Crossref configuration is incomplete: '.implode(', ', $missing)]);
        }
        if (! in_array($article->status, ['published', 'retracted'], true) || blank($article->doi) || $article->issue?->status !== 'published') {
            throw ValidationException::withMessages(['crossref' => 'A published article, assigned DOI and published issue are required.']);
        }
    }

    private function xml(JournalArticle $article, string $depositId): string
    {
        $translation = $article->translation('en') ?? $article->translation();
        $contributors = $article->authors->map(function ($author, int $position): string {
            $name = trim($author->latin_name ?: $author->name);
            $parts = preg_split('/\s+/u', $name) ?: [$name];
            $surname = array_pop($parts) ?: $name;
            $given = implode(' ', $parts);

            return '<person_name contributor_role="author" sequence="'.($position === 0 ? 'first' : 'additional').'">'
                .($given !== '' ? '<given_name>'.$this->escape($given).'</given_name>' : '')
                .'<surname>'.$this->escape($surname).'</surname>'
                .($author->orcid ? '<ORCID>https://orcid.org/'.$this->escape($author->orcid).'</ORCID>' : '')
                .'</person_name>';
        })->implode('');
        $date = $article->published_at;
        $resource = route('journal.public.articles.show', ['locale' => 'en', 'article' => $article->slug]);

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<doi_batch xmlns="http://www.crossref.org/schema/5.3.1" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" version="5.3.1" xsi:schemaLocation="http://www.crossref.org/schema/5.3.1 https://www.crossref.org/schemas/crossref5.3.1.xsd">'
            .'<head><doi_batch_id>'.$this->escape($depositId).'</doi_batch_id><timestamp>'.now()->utc()->format('YmdHis').'</timestamp>'
            .'<depositor><depositor_name>'.$this->escape((string) config('services.crossref.depositor_name')).'</depositor_name><email_address>'.$this->escape((string) config('services.crossref.depositor_email')).'</email_address></depositor>'
            .'<registrant>'.$this->escape($article->journal->publisher_name).'</registrant></head><body><journal><journal_metadata language="en">'
            .'<full_title>'.$this->escape($article->journal->localized('name', 'en')).'</full_title>'
            .($article->journal->issn ? '<issn media_type="print">'.$this->escape($article->journal->issn).'</issn>' : '')
            .($article->journal->eissn ? '<issn media_type="electronic">'.$this->escape($article->journal->eissn).'</issn>' : '')
            .'</journal_metadata><journal_issue><publication_date media_type="online"><month>'.$date?->format('m').'</month><day>'.$date?->format('d').'</day><year>'.$date?->format('Y').'</year></publication_date>'
            .'<journal_volume><volume>'.$article->issue->volume.'</volume></journal_volume><issue>'.$article->issue->number.'</issue></journal_issue>'
            .'<journal_article publication_type="full_text"><titles><title>'.$this->escape((string) $translation?->title).'</title></titles><contributors>'.$contributors.'</contributors>'
            .'<publication_date media_type="online"><month>'.$date?->format('m').'</month><day>'.$date?->format('d').'</day><year>'.$date?->format('Y').'</year></publication_date>'
            .($article->page_start ? '<pages><first_page>'.$article->page_start.'</first_page>'.($article->page_end ? '<last_page>'.$article->page_end.'</last_page>' : '').'</pages>' : '')
            .'<doi_data><doi>'.$this->escape($article->doi).'</doi><resource>'.$this->escape($resource).'</resource></doi_data></journal_article></journal></body></doi_batch>';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
