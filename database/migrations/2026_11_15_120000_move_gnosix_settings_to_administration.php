<?php

use Dotenv\Dotenv;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('platform_settings')->where('key', 'gnosix')->exists()) {
            return;
        }
        // Compatibility import only. Runtime code does not read these variables.
        // Read .env explicitly so an existing config cache cannot hide old values.
        $file = Dotenv::createArrayBacked(base_path())->safeLoad();
        $read = function (array $keys, mixed $fallback) use ($file): mixed {
            foreach ($keys as $key) {
                $process = getenv($key);
                if ($process !== false) {
                    return $process;
                }
                if (array_key_exists($key, $file)) {
                    return $file[$key];
                }
            }
            return $fallback;
        };
        $boolean = fn ($value, bool $fallback) => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $fallback;
        $existingInstallation = DB::table('platform_admins')->exists();
        $canonical = trim((string) $read(['GNOSIX_CANONICAL_URL'], 'https://gnosix.zion3d.ro'));
        $recipient = trim((string) $read(['GNOSIX_CONTACT_RECIPIENT', 'GNOSIX_PILOT_RECIPIENT'], 'raduta.alex@gmail.com'));
        $data = [
            'canonical_url' => \App\Support\SiteSettings::normalizeCanonicalUrl($canonical) ?? 'https://gnosix.zion3d.ro',
            'indexable' => $boolean($read(['GNOSIX_INDEXABLE'], false), false),
            'contact_enabled' => $boolean($read(['GNOSIX_CONTACT_ENABLED', 'GNOSIX_PILOT_ENABLED'], $existingInstallation), $existingInstallation),
            'contact_recipient' => filter_var($recipient, FILTER_VALIDATE_EMAIL) ? $recipient : 'raduta.alex@gmail.com',
        ];
        DB::table('platform_settings')->insert([
            'key' => 'gnosix', 'value' => Crypt::encryptString(json_encode($data, JSON_THROW_ON_ERROR)),
            'updated_by' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Preserve administrator configuration when rolling application code back.
        // The existing platform_settings table belongs to the earlier migration.
    }
};
