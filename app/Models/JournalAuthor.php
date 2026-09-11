<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class JournalAuthor extends Model
{
    protected $fillable = [
        'record_uuid', 'name', 'latin_name', 'email', 'orcid', 'country_code', 'biography',
        'created_by', 'updated_by',
    ];

    protected $hidden = ['email'];

    protected function casts(): array
    {
        return [
            'email' => 'encrypted',
            'biography' => 'array',
        ];
    }

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(JournalArticle::class, 'journal_article_author')
            ->withPivot(['position', 'is_corresponding', 'affiliation_name', 'affiliation_ror', 'contribution']);
    }
}
