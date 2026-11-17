<?php

// Versioned software contracts, not environment-driven business switches.
// Runtime opt-outs belong to organization_capabilities, edited in the workspace.
return [
    'profiles' => [
        'hospitality' => [
            'enabled' => true, 'icon' => 'hotel', 'schema_version' => 1,
            'kinds' => ['independent', 'company', 'portfolio'],
            'recommended_property_types' => ['hotel', 'guesthouse', 'apartment', 'mixed'],
            'capabilities' => ['dna.view', 'dna.manage', 'dna.review', 'hospitality.workspace', 'hospitality.buildings', 'hospitality.rooms', 'hospitality.maintenance', 'evidence.upload', 'evidence.review', 'evidence.conflicts', 'assets.view', 'assets.manage', 'assets.review', 'assets.types'],
        ],
        'construction' => [
            'enabled' => false, 'icon' => 'construction', 'schema_version' => 1,
            'kinds' => ['company', 'portfolio'],
            'recommended_property_types' => ['residential', 'hotel', 'commercial', 'mixed'],
            'capabilities' => ['construction.projects', 'construction.handover', 'evidence.upload', 'evidence.review', 'evidence.conflicts', 'assets.view', 'assets.manage', 'assets.review', 'assets.types'],
        ],
        'facility' => [
            'enabled' => false, 'icon' => 'facility', 'schema_version' => 1,
            'kinds' => ['company', 'portfolio'],
            'recommended_property_types' => ['commercial', 'apartment', 'hotel', 'mixed'],
            'capabilities' => ['facility.portfolio', 'facility.contracts', 'evidence.upload', 'evidence.review', 'evidence.conflicts', 'assets.view', 'assets.manage', 'assets.review', 'assets.types'],
        ],
    ],
    'capabilities' => [
        'dna.view' => ['requires' => 'assets.view', 'enabled' => true, 'operational' => true, 'icon' => '⌬', 'route' => 'app.dna.index', 'nav_label' => 'dna.title'],
        'dna.manage' => ['requires' => 'dna.view', 'enabled' => true, 'operational' => true, 'icon' => '⌬', 'route' => 'app.dna.index', 'nav_label' => 'dna.title'],
        'dna.review' => ['requires' => 'dna.view', 'enabled' => true, 'operational' => true, 'icon' => '⌬', 'route' => 'app.dna.index', 'nav_label' => 'dna.title'],
        'assets.view' => ['enabled' => true, 'operational' => true, 'icon' => '✦', 'route' => 'app.assets.index', 'nav_label' => 'asset.registry'],
        'assets.manage' => ['requires' => 'assets.view', 'enabled' => true, 'operational' => true, 'icon' => '✦', 'route' => 'app.assets.index', 'nav_label' => 'asset.registry'],
        'assets.review' => ['requires' => 'assets.view', 'enabled' => true, 'operational' => true, 'icon' => '◎', 'route' => 'app.assets.index', 'nav_label' => 'asset.registry'],
        'assets.types' => ['requires' => 'assets.view', 'enabled' => true, 'operational' => true, 'icon' => '◇', 'route' => 'app.assets.types', 'nav_label' => 'asset.types', 'manage_only' => true],
        'hospitality.workspace' => ['enabled' => true, 'operational' => true, 'icon' => '⌘', 'route' => 'app.studio', 'manage_only' => true],
        'hospitality.buildings' => ['enabled' => true, 'operational' => true, 'icon' => '◇', 'route' => 'app.properties.index'],
        'hospitality.rooms' => ['enabled' => true, 'operational' => true, 'icon' => '▤', 'route' => 'app.spaces.index'],
        'hospitality.maintenance' => ['enabled' => true, 'operational' => true, 'icon' => '⌘', 'route' => 'app.maintenance.index', 'nav_label' => 'maintenance.center'],
        'evidence.upload' => ['enabled' => true, 'operational' => true, 'icon' => '↥', 'route' => 'app.evidence.index', 'nav_label' => 'evidence.memory'],
        'evidence.review' => ['enabled' => true, 'operational' => true, 'icon' => '◎', 'route' => 'app.evidence.index', 'nav_label' => 'evidence.memory'],
        'evidence.conflicts' => ['enabled' => true, 'operational' => true, 'icon' => '⇄', 'route' => 'app.evidence.index', 'nav_label' => 'evidence.memory'],
        'construction.projects' => ['enabled' => false, 'operational' => false, 'icon' => '△'],
        'construction.handover' => ['enabled' => false, 'operational' => false, 'icon' => '↗'],
        'facility.portfolio' => ['enabled' => false, 'operational' => false, 'icon' => '▦'],
        'facility.contracts' => ['enabled' => false, 'operational' => false, 'icon' => '≡'],
    ],
    'role_denied_capabilities' => [
        'technician' => ['dna.manage', 'dna.review', 'evidence.upload', 'evidence.review', 'evidence.conflicts', 'assets.manage', 'assets.review', 'assets.types', 'hospitality.workspace'],
        'reporter' => ['dna.manage', 'dna.review', 'evidence.upload', 'evidence.review', 'evidence.conflicts', 'assets.manage', 'assets.review', 'assets.types', 'hospitality.workspace'],
    ],
    'navigation' => [
        ['route' => 'field.index', 'label' => 'field.portal', 'icon' => '↗'],
        ['route' => 'app.dashboard', 'label' => 'identity.overview', 'icon' => '◇'],
        ['route' => 'app.organizations.index', 'label' => 'workspace.organizations', 'icon' => '▦'],
        ['route' => 'app.account', 'label' => 'identity.account', 'icon' => '◎'],
    ],
];
