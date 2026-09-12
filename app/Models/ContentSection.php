<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ContentSection extends Model
{
    protected $fillable = ['slug', 'name', 'description', 'status', 'sort_order', 'created_by', 'updated_by'];
    protected function casts(): array { return ['name' => 'array', 'description' => 'array', 'sort_order' => 'integer']; }
    public function articles(): HasMany { return $this->hasMany(ContentArticle::class); }
    public function scopeActive(Builder $query): Builder { return $query->where('status', 'active'); }
    public function localized(string $field, ?string $locale = null): string
    {
        $values = $this->getAttribute($field); $locale ??= app()->getLocale();
        return is_array($values) ? trim((string) ($values[$locale] ?? $values['en'] ?? $values['ar'] ?? '')) : '';
    }
}
