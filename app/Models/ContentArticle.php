<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

final class ContentArticle extends Model
{
    protected $fillable = [
        'record_uuid', 'content_section_id', 'slug', 'title', 'excerpt', 'body', 'seo_title', 'seo_description',
        'image_alt', 'author_biography', 'tags', 'author_name', 'publisher_name', 'cover_image_path', 'source_url',
        'original_published_at', 'status',
        'is_featured', 'reading_minutes', 'views_count', 'pdf_downloads_count', 'revision', 'published_at', 'created_by', 'updated_by',
    ];
    protected function casts(): array
    {
        return ['title' => 'array', 'excerpt' => 'array', 'body' => 'array', 'seo_title' => 'array',
            'seo_description' => 'array', 'image_alt' => 'array', 'author_biography' => 'array', 'tags' => 'array',
            'is_featured' => 'boolean', 'published_at' => 'datetime', 'original_published_at' => 'datetime',
            'reading_minutes' => 'integer', 'views_count' => 'integer', 'pdf_downloads_count' => 'integer', 'revision' => 'integer'];
    }
    public function section(): BelongsTo { return $this->belongsTo(ContentSection::class, 'content_section_id'); }
    public function scopePublished(Builder $query): Builder { return $query->where('status', 'published')->whereNotNull('published_at')->where('published_at', '<=', now()); }
    public function localized(string $field, ?string $locale = null): string
    {
        $values = $this->getAttribute($field); $locale ??= app()->getLocale();
        return is_array($values) ? trim((string) ($values[$locale] ?? $values['en'] ?? $values['ar'] ?? '')) : '';
    }
    public function coverUrl(): ?string { return $this->cover_image_path ? url(Storage::disk('public')->url($this->cover_image_path)) : null; }
}
