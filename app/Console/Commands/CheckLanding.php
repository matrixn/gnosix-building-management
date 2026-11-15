<?php

namespace App\Console\Commands;

use App\Support\PilotAvailability;
use Illuminate\Console\Command;

class CheckLanding extends Command
{
    protected $signature = 'gnosix:check {--launch : Require production and live enquiry configuration}';

    protected $description = 'Verify landing assets and configuration without sending email.';

    public function handle(): int
    {
        $checks = [
            'Application key configured' => filled(config('app.key')),
            'Vite manifest exists' => is_file(public_path('build/manifest.json')),
            'Social image exists' => is_file(public_path('images/gnosix-social.png')),
            'Storage is writable' => is_writable(storage_path('framework/sessions')) && is_writable(storage_path('framework/cache/data')) && is_writable(storage_path('framework/views')),
            'Bootstrap cache is writable' => is_writable(base_path('bootstrap/cache')),
            'Design studies outside public root' => ! is_dir(public_path('concepts')),
        ];

        if ($this->option('launch')) {
            $mailer = config('mail.default');
            $checks += [
                'Production environment' => app()->environment('production'),
                'Debug disabled' => ! config('app.debug'),
                'Correct HTTPS application URL' => rtrim(config('app.url'), '/') === 'https://gnosix.zion3d.ro',
                'Correct canonical URL' => config('gnosix.canonical_url') === 'https://gnosix.zion3d.ro',
                'Secure encrypted sessions' => config('session.secure') && config('session.encrypt'),
                'Search indexing enabled' => config('gnosix.indexable'),
                'Pilot delivery configured' => PilotAvailability::ready(),
                'Synchronous enquiry delivery' => config('queue.default') === 'sync',
            ];
            if (config("mail.mailers.{$mailer}.transport") === 'smtp') {
                $checks['SMTP host configured'] = filled(config("mail.mailers.{$mailer}.host"));
                $checks['SMTP credentials configured'] = filled(config("mail.mailers.{$mailer}.username")) && filled(config("mail.mailers.{$mailer}.password"));
                $checks['SMTP scheme configured'] = in_array(config("mail.mailers.{$mailer}.scheme"), ['smtp', 'smtps'], true);
            }
        }

        foreach ($checks as $label => $passed) {
            $passed ? $this->info('OK: '.$label) : $this->error('FAIL: '.$label);
        }
        $this->line('Configuration checks only. SMTP acceptance, inbox delivery, DNS and TLS must be verified on the VPS.');

        return in_array(false, array_map(fn ($value) => (bool) $value, $checks), true) ? self::FAILURE : self::SUCCESS;
    }
}
