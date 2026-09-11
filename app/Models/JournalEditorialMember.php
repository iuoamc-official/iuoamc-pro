<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class JournalEditorialMember extends Model
{
    public const ROLES = [
        'editor_in_chief', 'managing_editor', 'section_editor',
        'reviewer', 'copy_editor', 'production_editor', 'advisory_board',
    ];

    public const STATUSES = ['draft', 'active', 'archived'];

    protected $fillable = [
        'record_uuid', 'journal_id', 'name', 'role', 'title', 'affiliation',
        'country_code', 'orcid', 'biography', 'status', 'sort_order',
        'consented_at', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'affiliation' => 'array',
            'biography' => 'array',
            'consented_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(static function (): never {
            throw new LogicException('Editorial appointments cannot be deleted; archive the appointment.');
        });
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function localized(string $field, ?string $locale = null): string
    {
        $values = $this->getAttribute($field);
        $locale ??= app()->getLocale();

        return is_array($values) ? trim((string) ($values[$locale] ?? $values['en'] ?? $values['ar'] ?? '')) : '';
    }
}
