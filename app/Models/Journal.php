<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Journal extends Model
{
    protected $fillable = [
        'record_uuid', 'code', 'name', 'description', 'publisher_name', 'issn', 'eissn',
        'status', 'settings', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'description' => 'array',
            'settings' => 'array',
        ];
    }

    public function issues(): HasMany
    {
        return $this->hasMany(JournalIssue::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(JournalArticle::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(JournalSubmission::class);
    }

    public function localized(string $field, ?string $locale = null): string
    {
        $values = $this->getAttribute($field);
        $locale ??= app()->getLocale();

        return is_array($values) ? trim((string) ($values[$locale] ?? $values['en'] ?? $values['ar'] ?? '')) : '';
    }
}
