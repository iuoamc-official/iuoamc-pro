<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class MembershipProfessionalTitle extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function localizedName(?string $locale = null): string
    {
        $locale = in_array($locale, ['ar', 'en', 'fr'], true) ? $locale : app()->getLocale();
        $field = 'name_'.$locale;

        return (string) ($this->getAttribute($field) ?: $this->name_en);
    }
}
