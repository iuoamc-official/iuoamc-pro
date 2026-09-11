<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class JournalArticleVersion extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'journal_article_id', 'version', 'kind', 'payload', 'payload_sha256', 'reason',
        'created_by', 'created_at',
    ];

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Journal version records are immutable.');
        });
        static::deleting(static function (): never {
            throw new LogicException('Journal version records cannot be deleted.');
        });
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(JournalArticle::class, 'journal_article_id');
    }
}
