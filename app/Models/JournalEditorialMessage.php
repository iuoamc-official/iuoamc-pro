<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class JournalEditorialMessage extends Model
{
    protected $fillable = ['record_uuid', 'journal_submission_id', 'sender_id', 'sender_role', 'body', 'sent_at'];

    protected $hidden = ['body'];

    protected function casts(): array
    {
        return ['body' => 'encrypted', 'sent_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Editorial correspondence is append-only.');
        });
        static::deleting(static function (): never {
            throw new LogicException('Editorial correspondence cannot be deleted.');
        });
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(JournalSubmission::class, 'journal_submission_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
