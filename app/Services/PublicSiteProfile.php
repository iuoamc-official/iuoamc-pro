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

        $profile = array_replace_recursive($this->defaults(), is_array($stored) ? $stored : []);

        $profile['entities'] = $this->defaults()['entities'];

        return $profile;
    }

    /** @return array<string, mixed> */
    public function defaults(): array
    {
        return [
            'brand_name' => 'IUOAMC',
            'legal_name' => 'International Union of Arab Master Chefs',
            'primary_logo' => 'assets/images/iuoamc-logo-gold-transparent.png',
            'contact_email' => 'info@iuoamc.uk',
            'announcement' => ['ar' => '', 'en' => '', 'fr' => ''],
            'registration_label' => 'UK Registered Company',
            'registration_number' => '16649793',
            'entities' => [
                [
                    'code' => 'IUOAMC',
                    'legal_name' => 'INTERNATIONAL UNION OF ARAB MASTER CHEFS LTD',
                    'logo' => 'assets/brand/master-v1/iuoamc-original.png',
                    'registrations' => [
                        'UK Company No.' => '16649793',
                        'UKPRN' => '10099301',
                        'ICO' => 'ZB971358',
                        'Canada OCN' => '1001411175',
                        'USA Wyoming ID' => '2025-001773195',
                    ],
                ],
                [
                    'code' => 'ICGA',
                    'registered_mark' => true,
                    'legal_name' => 'INTERNATIONAL CULINARY & GASTRONOMY ARBITRATION LTD',
                    'logo' => 'assets/brand/master-v1/icga-original.jpg',
                    'registrations' => [
                        'UK Company No.' => '16846998',
                        'UKPRN' => '10101250',
                        'ICO' => 'ZC146889',
                        'UK IPO Trademark (Registered)' => 'UK00004350642',
                        'USA Delaware File No.' => '10604085',
                    ],
                ],
                [
                    'code' => 'WSA-CA',
                    'legal_name' => 'WORLD SUPREME AUTHORITY FOR CULINARY ARBITRATION LTD',
                    'logo' => 'assets/brand/master-v1/wsaca-authority-seal-v3.webp',
                    'registrations' => ['Company No.' => '16896300', 'ICO' => 'ZC207157'],
                ],
                [
                    'code' => 'WSACT',
                    'legal_name' => 'WORLD SUPREME AUTHORITY FOR CULINARY TITLES LTD',
                    'logo' => 'assets/brand/master-v1/wsact-titles-authority-seal-v1.webp',
                    'registrations' => [],
                ],
                [
                    'code' => 'IUOAMC TV',
                    'legal_name' => 'IUOAMC MEDIA DIVISION',
                    'logo' => 'assets/brand/master-v1/iuoamc-tv-seal-v1.webp',
                    'registrations' => ['Parent Company No.' => '16649793'],
                ],
                [
                    'code' => 'WICP',
                    'legal_name' => 'WORLD CENTRE FOR INTELLECTUAL PROTECTION LTD',
                    'logo' => 'assets/brand/master-v1/wicp-original.webp',
                    'registrations' => ['Company No.' => 'SC882611'],
                ],
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
