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

    public function editorialMembers(): HasMany
    {
        return $this->hasMany(JournalEditorialMember::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(JournalSection::class);
    }

    public function localizedSetting(string $key, ?string $locale = null): string
    {
        $values = $this->setting($key, []);
        $locale ??= app()->getLocale();

        return is_array($values) ? trim((string) ($values[$locale] ?? $values['en'] ?? $values['ar'] ?? '')) : trim((string) $values);
    }

    public function notificationOutbox(): HasMany
    {
        return $this->hasMany(JournalNotificationOutbox::class);
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return ($this->settings ?? [])[$key] ?? $default;
    }

    public function isPubliclyLaunched(): bool
    {
        return $this->setting('public_launch_enabled', false) === true;
    }

    public function localized(string $field, ?string $locale = null): string
    {
        $values = $this->getAttribute($field);
        $locale ??= app()->getLocale();

        return is_array($values) ? trim((string) ($values[$locale] ?? $values['en'] ?? $values['ar'] ?? '')) : '';
    }
}
