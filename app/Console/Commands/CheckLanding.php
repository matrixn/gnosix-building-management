<?php

namespace App\Console\Commands;

use App\Support\PilotAvailability;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class CheckLanding extends Command
{
    protected $signature = 'gnosix:check {--production : Check production without requiring public pilot activation} {--launch : Require production and live enquiry configuration}';

    protected $description = 'Verify landing assets and configuration without sending email.';

    public function handle(): int
    {
        try {
            $databaseReady = Schema::hasTable('platform_admins') && Schema::hasTable('platform_admin_passkeys') && Schema::hasTable('platform_settings');
        } catch (\Throwable) {
            $databaseReady = false;
        }
        $checks = [
            'Application key configured' => filled(config('app.key')),
            'Vite manifest exists' => is_file(public_path('build/manifest.json')),
            'Social image exists' => is_file(public_path('images/gnosix-social.png')),
            'Storage is writable' => is_writable(storage_path('framework/sessions')) && is_writable(storage_path('framework/cache/data')) && is_writable(storage_path('framework/views')),
            'Bootstrap cache is writable' => is_writable(base_path('bootstrap/cache')),
            'Design studies outside public root' => ! is_dir(public_path('concepts')),
            'Platform database migrated' => $databaseReady,
        ];

        if ($this->option('production') || $this->option('launch')) {
            $checks += [
                'Production environment' => app()->environment('production'),
                'Debug disabled' => ! config('app.debug'),
                'Correct HTTPS application URL' => rtrim(config('app.url'), '/') === 'https://gnosix.zion3d.ro',
                'Correct canonical URL' => config('gnosix.canonical_url') === 'https://gnosix.zion3d.ro',
                'Secure encrypted sessions' => config('session.secure') && config('session.encrypt'),
                'Synchronous enquiry delivery' => config('queue.default') === 'sync',
                'Passkey origin configured' => config('platform.passkey_origin') === 'https://gnosix.zion3d.ro',
                'Passkey RP ID configured' => config('platform.passkey_rp_id') === 'gnosix.zion3d.ro',
            ];
        }
        if ($this->option('launch')) {
            $checks['Search indexing enabled'] = config('gnosix.indexable');
            $checks['Pilot delivery configured'] = $databaseReady && PilotAvailability::ready();
        }

        foreach ($checks as $label => $passed) {
            $passed ? $this->info('OK: '.$label) : $this->error('FAIL: '.$label);
        }
        $this->line('Configuration checks only. SMTP acceptance, inbox delivery, DNS and TLS must be verified on the VPS.');

        return in_array(false, array_map(fn ($value) => (bool) $value, $checks), true) ? self::FAILURE : self::SUCCESS;
    }
}
