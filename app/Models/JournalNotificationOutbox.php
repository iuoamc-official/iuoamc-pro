<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class JournalNotificationOutbox extends Model
{
    protected $table = 'journal_notification_outbox';

    protected $fillable = [
        'record_uuid', 'journal_id', 'event', 'recipient', 'recipient_hash', 'locale',
        'subject', 'template', 'payload', 'subject_type', 'subject_id', 'status',
        'attempts', 'available_at', 'sent_at', 'last_error',
    ];

    protected $hidden = ['recipient', 'recipient_hash', 'payload', 'last_error'];

    protected function casts(): array
    {
        return [
            'recipient' => 'encrypted',
            'payload' => 'encrypted:array',
            'available_at' => 'datetime',
            'sent_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(static function (): never {
            throw new LogicException('Notification outbox records cannot be deleted.');
        });
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }
}
