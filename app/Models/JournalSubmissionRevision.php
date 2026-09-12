<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class JournalSubmissionRevision extends Model
{
    protected $fillable = [
        'record_uuid', 'journal_submission_id', 'revision_number', 'manuscript_path',
        'original_filename', 'file_sha256', 'response_letter_path',
        'response_letter_filename', 'response_letter_sha256', 'author_note', 'status',
        'received_at',
    ];

    protected $hidden = ['manuscript_path', 'response_letter_path', 'author_note'];

    protected function casts(): array
    {
        return ['author_note' => 'encrypted', 'received_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Submitted manuscript revisions are immutable.');
        });
        static::deleting(static function (): never {
            throw new LogicException('Submitted manuscript revisions cannot be deleted.');
        });
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(JournalSubmission::class, 'journal_submission_id');
    }
}
