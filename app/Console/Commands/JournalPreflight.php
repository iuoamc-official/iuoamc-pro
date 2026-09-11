<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Journal;
use App\Services\JournalLaunchReadiness;
use Illuminate\Console\Command;

final class JournalPreflight extends Command
{
    protected $signature = 'journal:preflight';

    protected $description = 'Verify every mandatory journal launch condition without changing launch state.';

    public function handle(JournalLaunchReadiness $readiness): int
    {
        $journal = Journal::query()->where('status', 'active')->firstOrFail();
        $checks = $readiness->checks($journal);
        foreach ($checks as $key => $check) {
            $this->line(sprintf('[%s] %s — %s', $check['passed'] ? 'PASS' : 'FAIL', $key, $check['detail']));
        }

        return $readiness->isReady($journal) ? self::SUCCESS : self::FAILURE;
    }
}
