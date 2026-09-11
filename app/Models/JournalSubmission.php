<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class JournalSubmission extends Model
{
    public const STATUSES = ['submitted', 'screening', 'converted', 'declined'];

    protected $fillable = [
        'record_uuid', 'journal_id', 'converted_article_id', 'submission_code', 'status', 'type',
        'primary_locale', 'title', 'abstract', 'keywords', 'manuscript_path', 'original_filename',
        'file_sha256', 'author_name', 'author_email', 'author_email_hash', 'affiliation', 'orcid',
        'country_code', 'declarations', 'editorial_note', 'tracking_token_hash', 'consent_at',
        'received_at', 'handled_by',
    ];

    protected $hidden = [
        'author_email', 'author_email_hash', 'editorial_note', 'tracking_token_hash',
        'manuscript_path', 'handled_by',
    ];

    protected function casts(): array
    {
        return [
            'author_email' => 'encrypted',
            'editorial_note' => 'encrypted',
            'keywords' => 'array',
            'declarations' => 'array',
            'consent_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (JournalSubmission $submission): void {
            if (in_array($submission->getOriginal('status'), ['converted', 'declined'], true)) {
                throw new LogicException('A terminal journal submission record is immutable.');
            }
        });

        static::deleting(static function (): never {
            throw new LogicException('Journal submissions cannot be deleted; use an editorial status.');
        });
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function convertedArticle(): BelongsTo
    {
        return $this->belongsTo(JournalArticle::class, 'converted_article_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
