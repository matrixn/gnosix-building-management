<?php

return [
    'contact_enabled' => (bool) env('GNOSIX_CONTACT_ENABLED', true),
    'contact_recipient' => env('GNOSIX_CONTACT_RECIPIENT', env('GNOSIX_PILOT_RECIPIENT', 'raduta.alex@gmail.com')),
    'canonical_url' => rtrim(env('GNOSIX_CANONICAL_URL', 'https://gnosix.zion3d.ro'), '/'),
    'indexable' => (bool) env('GNOSIX_INDEXABLE', false),
];
