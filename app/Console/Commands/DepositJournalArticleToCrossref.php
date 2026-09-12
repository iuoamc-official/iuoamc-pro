<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\JournalArticle;
use App\Services\JournalCrossrefClient;
use Illuminate\Console\Command;
use Throwable;

final class DepositJournalArticleToCrossref extends Command
{
    protected $signature = 'journal:crossref-deposit
        {article : Journal article numeric ID or article code}
        {--test : Submit to the Crossref test endpoint without sealing production deposit state}
        {--confirm= : Required phrase DEPOSIT-CROSSREF}';

    protected $description = 'Deposit one published DOI metadata record with Crossref';

    public function handle(JournalCrossrefClient $crossref): int
    {
        if (! hash_equals('DEPOSIT-CROSSREF', (string) $this->option('confirm'))) {
            $this->error('CROSSREF_CONFIRMATION_REQUIRED');

            return self::INVALID;
        }

        $reference = (string) $this->argument('article');
        $article = JournalArticle::query()
            ->where(fn ($query) => $query->where('article_code', $reference)->when(ctype_digit($reference), fn ($numeric) => $numeric->orWhereKey((int) $reference)))
            ->firstOrFail();

        try {
            $result = $crossref->deposit($article, (bool) $this->option('test'));
            $this->info('CROSSREF_DEPOSIT_ACCEPTED');
            $this->line('DEPOSIT_ID='.$result['deposit_id']);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('CROSSREF_DEPOSIT_FAILED');
            $this->line($exception->getMessage());

            return self::FAILURE;
        }
    }
}
