<?php

namespace App\Evidence;

use App\Models\Evidence\Version;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Storage;

class ProviderInput
{
    public static function bytes(Version $v, string $provider): string
    {
        if (! app(Settings::class)->available() || app(Settings::class)->all()['provider'] !== $provider) {
            throw new \RuntimeException('provider_unavailable');
        }
        if (! Providers::supports($provider, $v->mime)) {
            throw new \RuntimeException('unsupported_format');
        }
        $bytes = Storage::disk('evidence')->get($v->path);
        if (! hash_equals($v->sha256, hash('sha256', $bytes))) {
            throw new \RuntimeException('integrity_failed');
        }

        return base64_encode($bytes);
    }

    public static function response(Response $response): array
    {
        if (! $response->successful()) {
            throw new \RuntimeException('provider_http_'.$response->status());
        }
        if (strlen($response->body()) > 2000000) {
            throw new \RuntimeException('provider_invalid');
        }
        $data = $response->json();
        if (! is_array($data)) {
            throw new \RuntimeException('provider_invalid');
        }

return $data;
    }
}
