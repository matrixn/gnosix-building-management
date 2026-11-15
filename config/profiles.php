<?php

// Versioned software contracts, not environment-driven business switches.
// Runtime opt-outs belong to organization_capabilities, edited in the workspace.
return [
    'profiles' => [
        'hospitality' => [
            'enabled' => true, 'icon' => 'hotel', 'schema_version' => 1,
            'kinds' => ['independent', 'company', 'portfolio'],
            'recommended_property_types' => ['hotel', 'guesthouse', 'apartment', 'mixed'],
            'capabilities' => ['hospitality.buildings', 'hospitality.rooms', 'hospitality.maintenance', 'evidence.upload', 'evidence.review', 'evidence.conflicts'],
        ],
        'construction' => [
            'enabled' => false, 'icon' => 'construction', 'schema_version' => 1,
            'kinds' => ['company', 'portfolio'],
            'recommended_property_types' => ['residential', 'hotel', 'commercial', 'mixed'],
            'capabilities' => ['construction.projects', 'construction.handover', 'evidence.upload', 'evidence.review', 'evidence.conflicts'],
        ],
        'facility' => [
            'enabled' => false, 'icon' => 'facility', 'schema_version' => 1,
            'kinds' => ['company', 'portfolio'],
            'recommended_property_types' => ['commercial', 'apartment', 'hotel', 'mixed'],
            'capabilities' => ['facility.portfolio', 'facility.contracts', 'evidence.upload', 'evidence.review', 'evidence.conflicts'],
        ],
    ],
    'capabilities' => [
        'hospitality.buildings' => ['enabled' => true, 'operational' => false, 'icon' => '◇'],
        'hospitality.rooms' => ['enabled' => true, 'operational' => false, 'icon' => '▤'],
        'hospitality.maintenance' => ['enabled' => true, 'operational' => false, 'icon' => '⌘'],
        'evidence.upload' => ['enabled' => true, 'operational' => false, 'icon' => '↥'],
        'evidence.review' => ['enabled' => true, 'operational' => false, 'icon' => '◎'],
        'evidence.conflicts' => ['enabled' => false, 'operational' => false, 'icon' => '⇄'],
        'construction.projects' => ['enabled' => false, 'operational' => false, 'icon' => '△'],
        'construction.handover' => ['enabled' => false, 'operational' => false, 'icon' => '↗'],
        'facility.portfolio' => ['enabled' => false, 'operational' => false, 'icon' => '▦'],
        'facility.contracts' => ['enabled' => false, 'operational' => false, 'icon' => '≡'],
    ],
    'navigation' => [
        ['route' => 'app.dashboard', 'label' => 'identity.overview', 'icon' => '◇'],
        ['route' => 'app.organizations.index', 'label' => 'workspace.organizations', 'icon' => '▦'],
        ['route' => 'app.account', 'label' => 'identity.account', 'icon' => '◎'],
    ],
];
