<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

final class JournalIssue extends Model
{
    protected $fillable = [
        'journal_id', 'volume', 'number', 'slug', 'title', 'description', 'cover_path',
        'status', 'published_at', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'description' => 'array',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (JournalIssue $issue): void {
            if ($issue->getOriginal('status') === 'published') {
                throw new LogicException('Published journal issues are immutable.');
            }
        });
        static::deleting(static function (): never {
            throw new LogicException('Journal issues cannot be deleted.');
        });
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(JournalArticle::class);
    }

    public function localized(string $field, ?string $locale = null): string
    {
        $values = $this->getAttribute($field);
        $locale ??= app()->getLocale();

        return is_array($values) ? trim((string) ($values[$locale] ?? $values['en'] ?? $values['ar'] ?? '')) : '';
    }
}
