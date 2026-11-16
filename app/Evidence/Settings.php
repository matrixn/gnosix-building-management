<?php

namespace App\Evidence;

use App\Models\PlatformSetting;

class Settings
{
    private function stored(): array
    {
        $s = PlatformSetting::find('evidence')?->value ?? [];
        // Adopt the original OpenAI configuration without a destructive migration.
        if (! isset($s['providers']) && isset($s['api_key'])) {
            $s['providers'][$s['provider'] ?? 'openai'] = ['model' => $s['model'] ?? '', 'api_key' => $s['api_key']];
        }

        return $s;
    }

    public function all(): array
    {
        return $this->forProvider($this->stored()['provider'] ?? 'openai');
    }

    public function forProvider(string $provider): array
    {
        $s = $this->stored();
        $active = $s['providers'][$provider] ?? [];

        return ['enabled' => $s['enabled'] ?? false, 'provider' => $provider, 'model' => $active['model'] ?? '', 'api_key' => $active['api_key'] ?? '', 'free_only' => $active['free_only'] ?? true, 'max_file_mb' => $s['max_file_mb'] ?? 8, 'building_quota_mb' => $s['building_quota_mb'] ?? 512];
    }

    public function available(): bool
    {
        $s = $this->all();
        $p = Providers::all()[$s['provider']] ?? null;

        return $s['enabled'] && $p && Providers::validModel($s['provider'], $s['model'], (bool) $s['free_only']) && (! $p['key'] || filled($s['api_key']));
    }

    public function save(array $values, int $admin): void
    {
        $s = $this->stored();
        $provider = $values['provider'];
        $p = $s['providers'][$provider] ?? [];
        $p['model'] = $values['model'] ?? '';
        $p['free_only'] = $values['free_only'] ?? true;
        if (filled($values['api_key'] ?? null)) {
            $p['api_key'] = $values['api_key'];
        }
        if ($values['clear_key'] ?? false) {
            $p['api_key'] = '';
        }
        if ($provider === 'ollama') {
            $p['api_key'] = '';
        }
        $s['providers'][$provider] = $p;
        foreach (['provider', 'enabled', 'max_file_mb', 'building_quota_mb'] as $key) {
            $s[$key] = $values[$key];
        }
        unset($s['api_key'],$s['model']);
        PlatformSetting::updateOrCreate(['key' => 'evidence'], ['value' => $s, 'updated_by' => $admin]);
    }
}
