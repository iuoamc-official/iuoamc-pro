<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class MembershipSubscriptionPlan extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'years' => 'integer',
            'fee_pence' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
