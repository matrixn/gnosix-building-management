<?php

namespace App\Maintenance;

use App\Models\PlatformSetting;

class Settings
{
    public function all(): array
    {
        $s = PlatformSetting::find('maintenance')?->value ?? [];

        return ['repeat_days' => max(7, min(3650, (int) ($s['repeat_days'] ?? 90))), 'repeat_threshold' => max(2, min(20, (int) ($s['repeat_threshold'] ?? 3)))];
    }
}
