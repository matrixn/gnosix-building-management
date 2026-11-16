<?php

return [
    'statuses' => ['unknown', 'in_service', 'out_of_service', 'stored', 'retired'],
    'fields' => ['manufacturer', 'model', 'serial_number', 'installation_date', 'purchase_date', 'purchase_vendor', 'purchase_amount', 'purchase_currency', 'warranty_start', 'warranty_end', 'warranty_terms', 'notes'],
    'evidence_fields' => ['equipment.manufacturer' => 'manufacturer', 'equipment.model' => 'model', 'equipment.serial_number' => 'serial_number', 'equipment.installation_date' => 'installation_date', 'equipment.warranty_end' => 'warranty_end', 'equipment.in_service' => 'in_service'],
    'types' => [
        'hvac' => ['icon' => '❄', 'attributes' => [['key' => 'power_kw', 'label' => 'Power / Putere', 'type' => 'number', 'unit' => 'kW'], ['key' => 'refrigerant', 'label' => 'Refrigerant / Agent frigorific', 'type' => 'string']]],
        'heating' => ['icon' => '♨', 'attributes' => [['key' => 'power_kw', 'label' => 'Power / Putere', 'type' => 'number', 'unit' => 'kW'], ['key' => 'capacity_l', 'label' => 'Capacity / Capacitate', 'type' => 'number', 'unit' => 'L']]],
        'electrical' => ['icon' => 'ϟ', 'attributes' => [['key' => 'voltage_v', 'label' => 'Voltage / Tensiune', 'type' => 'number', 'unit' => 'V']]],
        'plumbing' => ['icon' => '◉', 'attributes' => [['key' => 'flow_m3h', 'label' => 'Flow / Debit', 'type' => 'number', 'unit' => 'm³/h']]],
        'elevator' => ['icon' => '↕', 'attributes' => [['key' => 'load_kg', 'label' => 'Load / Sarcină', 'type' => 'number', 'unit' => 'kg']]],
        'fire_security' => ['icon' => '◇', 'attributes' => []], 'appliance' => ['icon' => '▣', 'attributes' => []], 'furniture' => ['icon' => '▤', 'attributes' => []], 'custom' => ['icon' => '✦', 'attributes' => []],
    ],
];
