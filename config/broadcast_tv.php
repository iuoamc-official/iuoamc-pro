<?php

return [
    'timezone' => env('IUOAMC_TV_TIMEZONE', 'Europe/London'),
    'legacy_url' => env('IUOAMC_TV_LEGACY_URL', 'https://platform-iuoamc.uk/tv'),
    'preview_url' => env('IUOAMC_TV_PREVIEW_URL'),
    'hls_url' => env('IUOAMC_TV_HLS_URL'),
    'status_url' => env('IUOAMC_TV_STATUS_URL'),
    'status_timeout' => (float) env('IUOAMC_TV_STATUS_TIMEOUT', 2.5),
    'public_enabled' => env('IUOAMC_TV_PUBLIC_ENABLED', false),
];
