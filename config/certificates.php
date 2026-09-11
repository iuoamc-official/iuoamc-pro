<?php

declare(strict_types=1);

return [
    'pades' => [
        'enabled' => (bool) env('IUOAMC_PADES_ENABLED', false),
        'binary' => env('IUOAMC_PADES_BINARY', '/opt/iuoamc-pades/bin/pyhanko'),
        'pkcs12_path' => env('IUOAMC_PADES_PKCS12_PATH'),
        'passphrase_file' => env('IUOAMC_PADES_PASSPHRASE_FILE'),
        'trust_root_path' => env('IUOAMC_PADES_TRUST_ROOT_PATH'),
        'timestamp_url' => env('IUOAMC_PADES_TIMESTAMP_URL'),
        'profile' => env('IUOAMC_PADES_PROFILE', 'PAdES-B-B'),
        'signature_field' => env(
            'IUOAMC_PADES_SIGNATURE_FIELD',
            '1/392,76,580,130/IUOAMC_Authorized_Signature',
        ),
        'visible_signature' => (bool) env('IUOAMC_PADES_VISIBLE_SIGNATURE', false),
        'timeout_seconds' => (int) env('IUOAMC_PADES_TIMEOUT', 90),
    ],
];
