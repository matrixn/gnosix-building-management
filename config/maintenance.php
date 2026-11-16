<?php

return [
    'categories' => ['cooling', 'hot_water', 'lock', 'leak', 'furniture', 'lighting', 'safety', 'other'],
    'severities' => ['low', 'normal', 'high', 'critical'],
    'impacts' => ['unknown', 'none', 'discomfort', 'unavailable', 'safety'],
    'states' => ['reported', 'triaged', 'assigned', 'in_progress', 'awaiting_parts', 'external_service', 'blocked', 'resolved', 'verified', 'closed', 'cancelled'],
    'terminal' => ['closed', 'cancelled'],
    'technician_states' => ['in_progress', 'awaiting_parts', 'external_service', 'blocked', 'resolved'],
    'frequencies' => ['daily', 'weekly', 'monthly', 'quarterly', 'annually', 'custom', 'usage'],
];
