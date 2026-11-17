<?php

// Versioned relation semantics. Runtime budgets are edited in platform administration.
$equipment = ['asset', 'system'];
$all = ['property', 'building', 'floor', 'space', 'asset', 'system'];

return ['types' => [
    'contains' => ['source' => $all, 'target' => $all, 'category' => 'containment', 'direction' => 'forward', 'impact' => false, 'cycles' => false, 'max_source' => 0, 'max_target' => 1, 'adapter' => true],
    'registered_to' => ['source' => $equipment, 'target' => ['property'], 'category' => 'scope', 'direction' => 'forward', 'impact' => false, 'cycles' => false, 'max_source' => 1, 'max_target' => 0, 'adapter' => true],
    'located_in' => ['source' => $equipment, 'target' => ['property', 'building', 'floor', 'space'], 'category' => 'location', 'direction' => 'forward', 'impact' => false, 'cycles' => false, 'max_source' => 1, 'max_target' => 0, 'adapter' => true],
    'part_of' => ['source' => $equipment, 'target' => ['system'], 'category' => 'containment', 'direction' => 'forward', 'impact' => false, 'cycles' => false, 'max_source' => 1, 'max_target' => 0, 'adapter' => true],
    'serves' => ['source' => $equipment, 'target' => ['space'], 'category' => 'service', 'direction' => 'forward', 'impact' => true, 'cycles' => false, 'max_source' => 0, 'max_target' => 0, 'adapter' => true],
    'supplies' => ['source' => $equipment, 'target' => [...$equipment, 'space'], 'category' => 'dependency', 'direction' => 'forward', 'impact' => true, 'cycles' => false, 'max_source' => 0, 'max_target' => 0],
    'feeds' => ['source' => $equipment, 'target' => [...$equipment, 'space'], 'category' => 'dependency', 'direction' => 'forward', 'impact' => true, 'cycles' => false, 'max_source' => 0, 'max_target' => 0],
    'powered_by' => ['source' => [...$equipment, 'space'], 'target' => $equipment, 'category' => 'dependency', 'direction' => 'reverse', 'impact' => true, 'cycles' => false, 'max_source' => 0, 'max_target' => 0],
    'depends_on' => ['source' => [...$equipment, 'space'], 'target' => $equipment, 'category' => 'dependency', 'direction' => 'reverse', 'impact' => true, 'cycles' => false, 'max_source' => 0, 'max_target' => 0],
    'connected_to' => ['source' => $all, 'target' => $all, 'category' => 'connectivity', 'direction' => 'both', 'impact' => false, 'cycles' => true, 'max_source' => 0, 'max_target' => 0],
]];
