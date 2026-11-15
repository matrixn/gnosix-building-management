<?php

return [
    'redirect_to_after_login' => '/admin/settings',
    'relying_party' => [
        'name' => 'GNOSIX Platform Administration',
        'id' => config('platform.passkey_rp_id'),
        'icon' => null,
    ],
    'models' => [
        'authenticatable' => \App\Models\PlatformAdmin::class,
        'passkey' => \App\Models\PlatformAdminPasskey::class,
    ],
    'actions' => [
        'configure_ceremony_step_manager_factory' => \App\Support\AdminPasskeyCeremony::class,
    ],
];
