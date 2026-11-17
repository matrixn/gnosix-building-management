<?php

namespace App\Dna;

use App\Models\PlatformSetting;

class Settings
{
    public function all(): array
    {
        $v = PlatformSetting::find('dna')?->value ?? [];

        return ['max_depth' => max(1, min(12, (int) ($v['max_depth'] ?? 6))), 'max_nodes' => max(40, min(300, (int) ($v['max_nodes'] ?? 160))), 'timeout_ms' => max(100, min(3000, (int) ($v['timeout_ms'] ?? 1200)))];
    }
}
