<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\JournalReview;
use App\Services\AuditTrail;
use App\Services\JournalNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class QueueJournalReviewReminders extends Command
{
    protected $signature = 'journal:queue-review-reminders {--days=3} {--limit=100}';

    protected $description = 'Queue bounded, deduplicated reminders for journal reviews approaching or past their due date.';

    public function handle(JournalNotificationService $notifications): int
    {
        $days = max(0, min((int) $this->option('days'), 30));
        $limit = max(1, min((int) $this->option('limit'), 500));
        $ids = JournalReview::query()
            ->whereIn('status', ['invited', 'in_progress'])
            ->whereNotNull('due_at')
            ->where('due_at', '<=', now()->addDays($days)->endOfDay())
            ->where(function ($query): void {
                $query->whereNull('last_reminded_at')->orWhere('last_reminded_at', '<', now()->subDay());
            })
            ->orderBy('due_at')
            ->limit($limit)
            ->pluck('id');

        $queued = 0;
        foreach ($ids as $id) {
            $didQueue = DB::transaction(function () use ($id, $notifications): bool {
                $review = JournalReview::query()
                    ->with(['article.journal', 'article.translations', 'reviewer'])
                    ->lockForUpdate()
                    ->find($id);
                if ($review === null || ! in_array($review->status, ['invited', 'in_progress'], true) || $review->due_at === null) {
                    return false;
                }
                if ($review->last_reminded_at !== null && $review->last_reminded_at->isAfter(now()->subDay())) {
                    return false;
                }

                $locale = in_array($review->reviewer->preferred_locale, ['ar', 'en', 'fr'], true) ? $review->reviewer->preferred_locale : 'en';
                $notifications->queue($review->article->journal, 'review_reminder', $review->reviewer->email, $locale, [
                    'name' => $review->reviewer->name,
                    'code' => $review->article->article_code,
                    'title' => $review->article->translation($locale)?->title ?? $review->article->article_code,
                    'round' => $review->round,
                    'due' => $review->due_at->toDateString(),
                    'workspace_url' => route('journal.control.articles.show', ['locale' => $locale, 'article' => $review->article]),
                ], $review);
                $review->update([
                    'last_reminded_at' => now()->utc()->startOfSecond(),
                    'reminder_count' => $review->reminder_count + 1,
                ]);
                AuditTrail::record('journal.review.reminder_queued', $review->article, [], [
                    'review_id' => $review->id,
                    'round' => $review->round,
                    'due_at' => $review->due_at->toIso8601String(),
                ]);

                return true;
            }, 5);
            $queued += $didQueue ? 1 : 0;
        }

        $this->info("Journal review reminders queued: {$queued}.");

        return self::SUCCESS;
    }
}
