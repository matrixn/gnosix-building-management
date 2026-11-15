<?php

namespace App\Support;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Schema;

class SiteSettings
{
    private ?array $snapshot = null;

    public static function defaults(): array
    {
        return [
            'canonical_url' => 'https://gnosix.zion3d.ro',
            'indexable' => false,
            'contact_enabled' => false,
            'contact_recipient' => 'raduta.alex@gmail.com',
        ];
    }

    public static function value(string $key): mixed
    {
        return app(self::class)->all()[$key];
    }

    public function all(): array
    {
        if ($this->snapshot === null) {
            $saved = Schema::hasTable('platform_settings') ? PlatformSetting::find('gnosix')?->value : null;
            $this->snapshot = array_replace(self::defaults(), $saved ?? []);
        }
        return $this->snapshot;
    }

    public function save(array $values, ?int $administratorId = null): void
    {
        $data = array_replace($this->all(), array_intersect_key($values, self::defaults()));
        PlatformSetting::updateOrCreate(['key' => 'gnosix'], ['value' => $data, 'updated_by' => $administratorId]);
        $this->reset();
    }

    public function reset(): void
    {
        $this->snapshot = null;
    }

    public static function normalizeCanonicalUrl(string $url): ?string
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }
        $parts = parse_url($url);
        if (! $parts || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true)
            || empty($parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || ! in_array($parts['path'] ?? '', ['', '/'], true)) {
            return null;
        }
        return rtrim($url, '/');
    }
}
