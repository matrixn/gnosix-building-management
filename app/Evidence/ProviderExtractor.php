<?php

namespace App\Evidence;

use App\Models\Evidence\Version;

class ProviderExtractor implements Extractor
{
    public function extract(Version $version): array
    {
        $s = app(Settings::class);
        $config = $s->all();
        if (! $s->available()) {
            throw new \RuntimeException('provider_unavailable');
        }
        if (! Providers::supports($config['provider'], $version->mime)) {
            throw new \RuntimeException('unsupported_format');
        }

        return app(Providers::all()[$config['provider']]['adapter'])->extract($version);
    }
}
