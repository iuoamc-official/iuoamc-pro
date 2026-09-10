<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\SystemSetting;

final class PublicSiteProfile
{
    public const SETTING_KEY = 'public_site_profile';

    /** @return array<string, mixed> */
    public function get(): array
    {
        $setting = SystemSetting::query()
            ->where('key', self::SETTING_KEY)
            ->where('is_public', true)
            ->whereIn('organization_id', Organization::query()->where('is_root', true)->select('id'))
            ->first();
        $stored = $setting?->value;

        return array_replace_recursive($this->defaults(), is_array($stored) ? $stored : []);
    }

    /** @return array<string, mixed> */
    public function defaults(): array
    {
        return [
            'brand_name' => 'IUOAMC',
            'legal_name' => 'International Union of Arab Master Chefs',
            'primary_logo' => 'assets/images/iuoamc-logo-gold-transparent.png',
            'contact_email' => 'iuomca2018@gmail.com',
            'announcement' => ['ar' => '', 'en' => '', 'fr' => ''],
            'registration_label' => 'UK Registered Company',
            'registration_number' => '16649793',
            'entities' => [
                ['code' => 'IUOAMC', 'logo' => 'assets/brand/master-v1/iuoamc-original.png'],
                ['code' => 'ICGA', 'logo' => 'assets/brand/master-v1/icga-original.jpg'],
                ['code' => 'WSACA', 'logo' => 'assets/brand/master-v1/wsaca-original.webp'],
                ['code' => 'WICP', 'logo' => 'assets/brand/master-v1/wicp-original.webp'],
            ],
        ];
    }

    /** @return list<string> */
    public function allowedLogos(): array
    {
        return [
            'assets/images/iuoamc-logo-gold-transparent.png',
            'assets/brand/iuoamc-pro-logo.png',
            'assets/brand/master-v1/iuoamc-original.png',
        ];
    }
}
