<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class JournalReview extends Model
{
    protected $fillable = [
        'journal_article_id', 'reviewer_id', 'round', 'status', 'recommendation',
        'author_comments', 'confidential_comments', 'due_at', 'submitted_at', 'assigned_by',
    ];

    protected $hidden = ['reviewer_id', 'confidential_comments'];

    protected function casts(): array
    {
        return [
            'confidential_comments' => 'encrypted',
            'due_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(JournalArticle::class, 'journal_article_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
