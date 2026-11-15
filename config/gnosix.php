<?php

return [
    'canonical_url' => rtrim(env('GNOSIX_CANONICAL_URL', 'https://gnosix.zion3d.ro'), '/'),
    'indexable' => (bool) env('GNOSIX_INDEXABLE', false),
    'pilot_enabled' => (bool) env('GNOSIX_PILOT_ENABLED', false),
    'pilot_recipient' => env('GNOSIX_PILOT_RECIPIENT'),
];
