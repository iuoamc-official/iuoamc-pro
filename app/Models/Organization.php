<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    protected $fillable = [
        'parent_id',
        'code',
        'legal_name',
        'display_name',
        'jurisdiction',
        'registration_number',
        'status',
        'is_root',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_root' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['title', 'is_primary'])
            ->withTimestamps();
    }
}
