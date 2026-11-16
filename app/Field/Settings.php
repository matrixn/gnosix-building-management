<?php

namespace App\Field;

use App\Models\PlatformSetting;

class Settings
{
    public function all(): array
    {
        $v = PlatformSetting::find('field')?->value ?? [];

        return ['invite_max_days' => max(1, min(365, (int) ($v['invite_max_days'] ?? 90)))];
    }
}
