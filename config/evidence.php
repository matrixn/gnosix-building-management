<?php

return [
    'document_types' => ['invoice', 'warranty', 'manual', 'maintenance', 'installation', 'inspection', 'technical', 'unknown'],
    'keys' => ['building.construction_year' => 'integer', 'equipment.serial_number' => 'string', 'equipment.manufacturer' => 'string', 'equipment.model' => 'string', 'equipment.in_service' => 'boolean', 'equipment.installation_date' => 'date', 'equipment.warranty_end' => 'date', 'maintenance.performed_on' => 'date', 'inspection.next_due' => 'date', 'document.reference' => 'string', 'space.area_m2' => 'number', 'custom.note' => 'string'],
    'statuses' => ['proposed', 'confirmed', 'rejected', 'disputed', 'superseded'],
    'max_facts' => 100, 'max_files' => 5, 'max_pages' => 300,
];
