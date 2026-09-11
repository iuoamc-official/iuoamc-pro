<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class JournalArticleTranslation extends Model
{
    protected $fillable = [
        'journal_article_id', 'locale', 'title', 'subtitle', 'abstract', 'body', 'keywords',
        'references', 'seo_title', 'seo_description',
    ];

    protected function casts(): array
    {
        return [
            'keywords' => 'array',
            'references' => 'array',
        ];
    }

    protected static function booted(): void
    {
        $guard = static function (JournalArticleTranslation $translation): void {
            if (in_array($translation->article()->value('status'), ['published', 'retracted'], true)) {
                throw new LogicException('Published journal content is immutable. Create a correction instead.');
            }
        };

        static::creating($guard);
        static::updating($guard);
        static::deleting($guard);
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(JournalArticle::class, 'journal_article_id');
    }
}
