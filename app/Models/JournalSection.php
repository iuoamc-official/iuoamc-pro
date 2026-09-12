<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class JournalSection extends Model
{
    public const SCOPES = ['all', 'peer_reviewed_research', 'professional_article'];

    protected $fillable = ['journal_id', 'slug', 'name', 'description', 'scope', 'status', 'sort_order', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['name' => 'array', 'description' => 'array', 'sort_order' => 'integer'];
    }

    public function journal(): BelongsTo { return $this->belongsTo(Journal::class); }

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(JournalArticle::class, 'journal_article_section')->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder { return $query->where('status', 'active'); }

    public function localized(string $field, ?string $locale = null): string
    {
        $values = $this->getAttribute($field);
        $locale ??= app()->getLocale();
        return is_array($values) ? trim((string) ($values[$locale] ?? $values['en'] ?? $values['ar'] ?? '')) : '';
    }
}
