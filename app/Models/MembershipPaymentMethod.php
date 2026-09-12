<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class MembershipPaymentMethod extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'requires_waiver_reason' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function localizedName(?string $locale = null): string
    {
        $locale = in_array($locale, ['ar', 'en', 'fr'], true) ? $locale : app()->getLocale();

        return (string) ($this->getAttribute('name_'.$locale) ?: $this->name_en);
    }
}
