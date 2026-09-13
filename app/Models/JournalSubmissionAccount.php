<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class JournalSubmissionAccount extends Model
{
    protected $fillable = [
        'journal_submission_id', 'user_id', 'link_method', 'linked_at',
    ];

    protected function casts(): array
    {
        return ['linked_at' => 'datetime'];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(JournalSubmission::class, 'journal_submission_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
