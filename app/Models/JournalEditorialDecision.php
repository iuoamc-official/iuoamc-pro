<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class JournalEditorialDecision extends Model
{
    protected $fillable = [
        'journal_article_id', 'decision', 'letter', 'confidential_note', 'issued_by', 'issued_at',
    ];

    protected $hidden = ['confidential_note', 'issued_by'];

    protected function casts(): array
    {
        return [
            'confidential_note' => 'encrypted',
            'issued_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Editorial decisions are immutable. Issue a new decision instead.');
        });
        static::deleting(static function (): never {
            throw new LogicException('Editorial decisions are part of the permanent editorial record.');
        });
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(JournalArticle::class, 'journal_article_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
