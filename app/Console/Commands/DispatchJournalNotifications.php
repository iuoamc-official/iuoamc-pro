<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\JournalNotificationService;
use Illuminate\Console\Command;

final class DispatchJournalNotifications extends Command
{
    protected $signature = 'journal:dispatch-notifications {--limit=50}';

    protected $description = 'Dispatch a bounded batch from the durable journal notification outbox.';

    public function handle(JournalNotificationService $notifications): int
    {
        $result = $notifications->dispatchPending((int) $this->option('limit'));
        $this->info("Journal notifications: {$result['sent']} sent; {$result['failed']} failed.");

        return $result['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
