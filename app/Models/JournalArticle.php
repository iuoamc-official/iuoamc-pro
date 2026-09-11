<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

final class JournalArticle extends Model
{
    public const TYPES = ['peer_reviewed_research', 'professional_article'];

    protected $fillable = [
        'record_uuid', 'journal_id', 'journal_issue_id', 'correction_of_id', 'article_code',
        'slug', 'type', 'status', 'primary_locale', 'doi', 'license', 'page_start', 'page_end',
        'received_at', 'accepted_at', 'published_at', 'retracted_at', 'declarations', 'pdf_path',
        'pdf_sha256', 'pdf_size', 'pdf_downloads_count', 'wicp_registration_number',
        'wicp_registered_at', 'wicp_verification_url', 'wicp_verified_at',
        'lock_version', 'version_of_record', 'version_of_record_hash', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'date',
            'accepted_at' => 'date',
            'published_at' => 'datetime',
            'retracted_at' => 'datetime',
            'wicp_registered_at' => 'date',
            'wicp_verified_at' => 'datetime',
            'declarations' => 'array',
            'pdf_size' => 'integer',
            'pdf_downloads_count' => 'integer',
            'lock_version' => 'integer',
            'version_of_record' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (JournalArticle $article): void {
            if (! in_array($article->getOriginal('status'), ['published', 'retracted'], true)) {
                return;
            }

            $allowed = ['status', 'retracted_at', 'lock_version', 'updated_by', 'updated_at', 'pdf_downloads_count'];
            $changed = array_keys($article->getDirty());
            if (array_diff($changed, $allowed) !== []) {
                throw new LogicException('A published journal version of record is immutable. Create a correction instead.');
            }
        });

        static::deleting(static function (): never {
            throw new LogicException('Journal articles cannot be deleted; use the editorial status lifecycle.');
        });
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(JournalIssue::class, 'journal_issue_id');
    }

    public function correctionOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'correction_of_id');
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(self::class, 'correction_of_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(JournalArticleTranslation::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(JournalArticleVersion::class)->orderByDesc('version');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(JournalReview::class);
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(JournalEditorialDecision::class)->orderByDesc('issued_at');
    }

    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(JournalAuthor::class, 'journal_article_author')
            ->withPivot(['position', 'is_corresponding', 'affiliation_name', 'affiliation_ror', 'contribution'])
            ->orderByPivot('position');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->whereIn('status', ['published', 'retracted'])
            ->whereNotNull('published_at')
            ->whereHas('issue', fn (Builder $issueQuery): Builder => $issueQuery->where('status', 'published')->whereNotNull('published_at'));
    }

    public function translation(?string $locale = null): ?JournalArticleTranslation
    {
        $locale ??= app()->getLocale();

        return $this->translations->firstWhere('locale', $locale)
            ?? $this->translations->firstWhere('locale', 'en')
            ?? $this->translations->first();
    }
}
