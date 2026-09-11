<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\JournalWorkflowMail;
use App\Models\Journal;
use App\Models\JournalNotificationOutbox;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

final class JournalNotificationService
{
    /** @param array<string, scalar|null> $payload */
    public function queue(
        Journal $journal,
        string $event,
        string $email,
        string $locale,
        array $payload,
        ?Model $subject = null,
    ): JournalNotificationOutbox {
        $email = Str::lower(trim($email));
        $locale = in_array($locale, ['ar', 'en', 'fr'], true) ? $locale : 'en';

        return JournalNotificationOutbox::query()->create([
            'record_uuid' => (string) Str::uuid(),
            'journal_id' => $journal->id,
            'event' => $event,
            'recipient' => $email,
            'recipient_hash' => hash_hmac('sha256', $email, (string) config('app.key')),
            'locale' => $locale,
            'subject' => trans('journal.mail.subjects.'.$event, $payload, $locale),
            'template' => 'journal-workflow',
            'payload' => $payload,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'status' => 'pending',
            'attempts' => 0,
            'available_at' => now()->utc()->startOfSecond(),
        ]);
    }

    /** @return array{sent: int, failed: int} */
    public function dispatchPending(int $limit = 50): array
    {
        $sent = 0;
        $failed = 0;
        JournalNotificationOutbox::query()
            ->where('status', 'sending')
            ->where('updated_at', '<', now()->subMinutes(15))
            ->where('attempts', '<', 5)
            ->update(['status' => 'pending', 'available_at' => now()]);
        JournalNotificationOutbox::query()
            ->where('status', 'sending')
            ->where('updated_at', '<', now()->subMinutes(15))
            ->where('attempts', '>=', 5)
            ->update(['status' => 'failed', 'last_error' => 'Delivery worker stopped before acknowledging the message.']);
        $ids = JournalNotificationOutbox::query()
            ->where('status', 'pending')
            ->where('available_at', '<=', now())
            ->orderBy('id')
            ->limit(max(1, min($limit, 200)))
            ->pluck('id');

        foreach ($ids as $id) {
            $message = $this->claim((int) $id);
            if ($message === null) {
                continue;
            }

            try {
                Mail::to($message->recipient)->send(new JournalWorkflowMail($message));
                $message->update([
                    'status' => 'sent',
                    'sent_at' => now()->utc()->startOfSecond(),
                    'last_error' => null,
                ]);
                $sent++;
            } catch (Throwable $exception) {
                $attempts = $message->attempts;
                $message->update([
                    'status' => $attempts >= 5 ? 'failed' : 'pending',
                    'available_at' => now()->addMinutes(min(60, 2 ** $attempts)),
                    'last_error' => $exception::class,
                ]);
                report($exception);
                $failed++;
            }
        }

        return compact('sent', 'failed');
    }

    private function claim(int $id): ?JournalNotificationOutbox
    {
        return DB::transaction(function () use ($id): ?JournalNotificationOutbox {
            $message = JournalNotificationOutbox::query()->lockForUpdate()->find($id);
            if ($message === null || $message->status !== 'pending' || $message->available_at->isFuture()) {
                return null;
            }

            $message->update([
                'status' => 'sending',
                'attempts' => $message->attempts + 1,
            ]);

            return $message->fresh();
        }, 5);
    }
}
