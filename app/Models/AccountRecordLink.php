<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AccountRecordLink extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['email_hmac'];

    protected function casts(): array
    {
        return ['matched_at' => 'immutable_datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
