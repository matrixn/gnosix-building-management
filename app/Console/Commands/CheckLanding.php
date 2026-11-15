<?php

namespace App\Console\Commands;

use App\Support\ContactAvailability;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class CheckLanding extends Command
{
    protected $signature = 'gnosix:check {--production : Check production without requiring public contact activation} {--launch : Require production and live contact configuration}';

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
            'English social image exists' => is_file(public_path('images/gnosix-social-en.png')),
            'Storage is writable' => is_writable(storage_path('framework/sessions')) && is_writable(storage_path('framework/cache/data')) && is_writable(storage_path('framework/views')),
            'Bootstrap cache is writable' => is_writable(base_path('bootstrap/cache')),
            'Design studies outside public root' => ! is_dir(public_path('concepts')),
            'Platform database migrated' => $databaseReady,
            'Customer identity migrated' => $databaseReady && Schema::hasColumn('users', 'auth_version') && Schema::hasColumn('users', 'onboarding_completed_at'),
            'Organization profiling migrated' => $databaseReady && collect(['organizations', 'organization_memberships', 'organization_profiles', 'organization_capabilities', 'user_preferences', 'onboarding_sessions'])->every(fn ($table) => Schema::hasTable($table)),
        ];

        if ($this->option('production') || $this->option('launch')) {
            $canonical = $databaseReady ? \App\Support\SiteSettings::value('canonical_url') : '';
            $checks += [
                'Production environment' => app()->environment('production'),
                'Debug disabled' => ! config('app.debug'),
                'Correct HTTPS application URL' => rtrim(config('app.url'), '/') === 'https://gnosix.zion3d.ro',
                'Canonical URL uses HTTPS' => \App\Support\SiteSettings::normalizeCanonicalUrl($canonical) !== null && str_starts_with($canonical, 'https://'),
                'Secure encrypted sessions' => config('session.secure') && config('session.encrypt'),
                'HTTP-only SameSite sessions' => config('session.http_only') && in_array(config('session.same_site'), ['lax', 'strict'], true),
                'Server-side session storage' => in_array(config('session.driver'), ['file', 'database', 'redis', 'memcached', 'dynamodb'], true),
                'Synchronous enquiry delivery' => config('queue.default') === 'sync',
                'Passkey origin configured' => config('platform.passkey_origin') === 'https://gnosix.zion3d.ro',
                'Passkey RP ID configured' => config('platform.passkey_rp_id') === 'gnosix.zion3d.ro',
            ];
        }
        if ($this->option('launch')) {
            $checks['Search indexing enabled'] = \App\Support\SiteSettings::value('indexable');
            $checks['Contact delivery configured'] = $databaseReady && ContactAvailability::ready();
        }

        foreach ($checks as $label => $passed) {
            $passed ? $this->info('OK: '.$label) : $this->error('FAIL: '.$label);
        }
        $this->line('Configuration checks only. SMTP acceptance, inbox delivery, DNS and TLS must be verified on the VPS.');

        return in_array(false, array_map(fn ($value) => (bool) $value, $checks), true) ? self::FAILURE : self::SUCCESS;
    }
}
