<?php

return [
    'property_types' => ['house', 'cabin', 'guesthouse', 'hostel', 'hotel', 'resort', 'apartment', 'mixed', 'other'],
    'property_visuals' => ['house' => 'house', 'cabin' => 'cabin', 'guesthouse' => 'guesthouse', 'hostel' => 'hostel', 'hotel' => 'hotel', 'resort' => 'resort', 'apartment' => 'apartment', 'mixed' => 'mixed', 'other' => 'other'],
    'space_types' => ['room', 'apartment', 'dormitory', 'shared_room', 'technical', 'corridor', 'storage', 'common', 'house', 'other'],
    'amenities' => ['wifi', 'parking', 'pool', 'kitchen', 'accessible', 'meeting'],
    'facilities' => ['reception', 'pool', 'dining', 'garden'],
    'accommodation_types' => ['room', 'apartment', 'house'],
    'scenarios' => [
        'cabin' => ['icon' => '⌂', 'steps' => ['vision', 'review'], 'defaults' => ['property_type' => 'cabin', 'building_count' => 1]],
        'guesthouse' => ['icon' => '◇', 'defaults' => ['property_type' => 'guesthouse', 'building_count' => 1, 'floor_count' => 1, 'room_count' => 5]],
        'hostel' => ['icon' => '▤', 'defaults' => ['property_type' => 'hostel', 'building_count' => 1, 'floor_count' => 1, 'dormitory_count' => 2, 'beds_per_dorm' => 6]],
        'hotel' => ['icon' => '▥', 'defaults' => ['property_type' => 'hotel', 'building_count' => 1, 'floor_count' => 4, 'room_count' => 40]],
        'resort' => ['icon' => '▦', 'defaults' => ['property_type' => 'resort', 'building_count' => 3, 'floor_count' => 1, 'room_count' => 30]],
        'group' => ['icon' => '⌘', 'defaults' => ['property_type' => 'hotel', 'property_count' => 3, 'building_count' => 1, 'floor_count' => 4, 'room_count' => 40]],
    ],
    // Conditions are shared by the PHP evaluator and the browser. Hidden answers
    // remain in the draft, but hidden_value is used for materializing the plan.
    'fields' => [
        'property_name' => ['type' => 'text', 'step' => 'vision', 'required' => true, 'max' => 150, 'default' => ''],
        'property_type' => ['type' => 'select', 'step' => 'vision', 'required' => true, 'options' => 'property_types', 'default' => 'cabin'],
        'group_name' => ['type' => 'text', 'step' => 'vision', 'required' => true, 'max' => 150, 'when' => ['scenario' => ['group']], 'hidden_value' => '', 'default' => ''],
        'legal_entity_name' => ['type' => 'text', 'step' => 'vision', 'max' => 150, 'when' => ['scenario' => ['group']], 'hidden_value' => '', 'default' => ''],
        'property_count' => ['type' => 'number', 'step' => 'structure', 'min' => 1, 'max' => 50, 'default' => 1, 'when' => ['scenario' => ['group']], 'hidden_value' => 1],
        'building_count' => ['type' => 'number', 'step' => 'structure', 'min' => 0, 'max' => 30, 'default' => 1, 'when' => ['scenario' => ['guesthouse', 'hostel', 'hotel', 'resort', 'group']], 'hidden_value' => 1],
        'floor_count' => ['type' => 'number', 'step' => 'structure', 'min' => 0, 'max' => 40, 'default' => 0, 'when' => ['scenario' => ['guesthouse', 'hostel', 'hotel', 'resort', 'group'], 'building_count' => ['min' => 1]], 'hidden_value' => 0],
        'room_count' => ['type' => 'number', 'step' => 'structure', 'min' => 0, 'max' => 500, 'default' => 0, 'when' => ['scenario' => ['guesthouse', 'hostel', 'hotel', 'resort', 'group']], 'hidden_value' => 0],
        'accommodation_type' => ['type' => 'select', 'step' => 'structure', 'required' => true, 'options' => 'accommodation_types', 'default' => 'room', 'when' => ['room_count' => ['min' => 1]], 'hidden_value' => 'room'],
        'dormitory_count' => ['type' => 'number', 'step' => 'structure', 'min' => 0, 'max' => 100, 'default' => 0, 'when' => ['scenario' => ['hostel']], 'hidden_value' => 0],
        'beds_per_dorm' => ['type' => 'number', 'step' => 'structure', 'min' => 1, 'max' => 100, 'default' => 6, 'when' => ['scenario' => ['hostel'], 'dormitory_count' => ['min' => 1]], 'hidden_value' => 0],
        'numbering_start' => ['type' => 'number', 'step' => 'structure', 'min' => 1, 'max' => 999999, 'default' => 101, 'when' => ['scenario' => ['guesthouse', 'hostel', 'hotel', 'resort', 'group']], 'hidden_value' => 1],
        'numbering_prefix' => ['type' => 'text', 'step' => 'structure', 'max' => 15, 'default' => '', 'when' => ['scenario' => ['guesthouse', 'hostel', 'hotel', 'resort', 'group']], 'hidden_value' => ''],
        'amenities' => ['type' => 'checks', 'step' => 'structure', 'options' => 'amenities', 'default' => [], 'hidden_value' => []],
        'shared_facilities' => ['type' => 'checks', 'step' => 'structure', 'options' => 'facilities', 'default' => [], 'when' => ['scenario' => ['resort']], 'hidden_value' => []],
    ],
    'steps' => ['vision', 'structure', 'review'],
    'batch_node_limit' => 5000,
];
