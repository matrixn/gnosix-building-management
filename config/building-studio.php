<?php

return [
    'presets' => [
        'house' => ['levels' => 1, 'spaces' => 0, 'icon' => '⌂'], 'cabin' => ['levels' => 1, 'spaces' => 0, 'icon' => '△'],
        'guesthouse' => ['levels' => 1, 'spaces' => 5, 'icon' => '◇'], 'hostel' => ['levels' => 1, 'spaces' => 2, 'space_type' => 'dormitory', 'beds' => 6, 'icon' => '▤'],
        'hotel' => ['levels' => 4, 'spaces' => 10, 'icon' => '▥'], 'resort' => ['levels' => 1, 'spaces' => 0, 'icon' => '▦'],
        'apartment_building' => ['levels' => 3, 'spaces' => 2, 'space_type' => 'apartment', 'icon' => '▧'], 'mixed' => ['levels' => 2, 'spaces' => 0, 'icon' => '◈'], 'custom' => ['levels' => 0, 'spaces' => 0, 'icon' => '✦'],
    ],
    'level_kinds' => ['ground', 'upper', 'basement', 'mezzanine', 'attic', 'custom'],
    'space_types' => ['room', 'bedroom', 'living', 'kitchen', 'bathroom', 'apartment', 'dormitory', 'shared_room', 'technical', 'corridor', 'storage', 'common', 'house', 'other'],
    'unit_types' => ['whole_building', 'room', 'suite', 'apartment', 'dormitory', 'bed', 'custom'],
    'identity_fields' => ['name', 'display_name', 'reference', 'classification', 'custom_classification', 'address', 'country', 'timezone', 'latitude', 'longitude', 'construction_year', 'state'],
    'max_levels' => 100, 'max_spaces' => 5000, 'max_beds' => 10000, 'max_units' => 5000, 'max_payload_bytes' => 2097152,
];
