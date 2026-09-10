<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicPage extends Model
{
    protected $fillable = [
        'slug', 'template', 'navigation_label', 'eyebrow', 'title', 'summary', 'body',
        'seo_title', 'seo_description', 'navigation_order', 'show_in_navigation',
        'status', 'revision', 'created_by', 'updated_by', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'navigation_label' => 'array',
            'eyebrow' => 'array',
            'title' => 'array',
            'summary' => 'array',
            'body' => 'array',
            'seo_title' => 'array',
            'seo_description' => 'array',
            'show_in_navigation' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')->whereNotNull('published_at');
    }

    public function localized(string $field, ?string $locale = null): string
    {
        $values = $this->getAttribute($field);
        if (! is_array($values)) {
            return '';
        }

        $locale ??= app()->getLocale();

        return trim((string) ($values[$locale] ?? $values['en'] ?? $values['ar'] ?? ''));
    }
}
