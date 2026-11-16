<?php

namespace App\Evidence;

use App\Models\PlatformSetting;

class Settings
{
    public function all(): array
    {
        return array_replace(['enabled' => false, 'provider' => 'openai', 'model' => '', 'api_key' => '', 'max_file_mb' => 8, 'building_quota_mb' => 512], PlatformSetting::find('evidence')?->value ?? []);
    }

    public function available(): bool
    {
        $s = $this->all();

        return $s['enabled'] && $s['provider'] === 'openai' && filled($s['model']) && filled($s['api_key']);
    }

    public function save(array $values, int $admin): void
    {
        $s = $this->all();
        if (! filled($values['api_key'] ?? null)) {
            unset($values['api_key']);
        }if ($values['clear_key'] ?? false) {
            $values['api_key'] = '';
        }unset($values['clear_key']);
        PlatformSetting::updateOrCreate(['key' => 'evidence'], ['value' => array_replace($s, $values), 'updated_by' => $admin]);
    }
}
