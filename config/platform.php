<?php

return [
    'passkey_origin' => rtrim(env('PASSKEY_ORIGIN', env('APP_URL', 'http://localhost')), '/'),
    'passkey_rp_id' => env('PASSKEY_RP_ID', parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
    'recent_auth_seconds' => 600,
];
