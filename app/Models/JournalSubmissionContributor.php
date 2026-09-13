<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class JournalSubmissionContributor extends Model
{
    protected $fillable = [
        'journal_submission_id', 'position', 'is_corresponding', 'name', 'latin_name',
        'email', 'email_hash', 'orcid', 'affiliation_name', 'affiliation_ror',
        'country_code', 'contribution_roles', 'consent_confirmed_at',
    ];

    protected $hidden = ['email', 'email_hash'];

    protected function casts(): array
    {
        return [
            'email' => 'encrypted',
            'is_corresponding' => 'boolean',
            'contribution_roles' => 'array',
            'consent_confirmed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Submission contributor records are immutable.');
        });
        static::deleting(static function (): never {
            throw new LogicException('Submission contributor records cannot be deleted.');
        });
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(JournalSubmission::class, 'journal_submission_id');
    }
}
