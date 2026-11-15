<?php

namespace App\Mail;

use App\Support\SmtpSettings;
use Illuminate\Mail\MailManager;

class PlatformMailManager extends MailManager
{
    public function mailer($name = null)
    {
        // Standard Mail APIs, named mailers, notifications and queued mail share SMTP.
        // Resolve afresh for each message so long-running workers see saved changes.
        $this->purge('smtp');
        $s = $this->app->make(SmtpSettings::class)->resolved();
        $this->app['config']->set('mail.mailers.smtp', [
            'transport' => 'smtp', 'scheme' => $s['scheme'],
            'host' => $s['host'], 'port' => (int) $s['port'],
            'username' => $s['username'], 'password' => $s['password'],
            'timeout' => 15,
            'require_tls' => $this->app->environment('production'),
            'local_domain' => parse_url(config('app.url'), PHP_URL_HOST),
        ]);
        $this->app['config']->set('mail.from', ['address' => $s['from_address'], 'name' => $s['from_name']]);
        return parent::mailer('smtp');
    }

    public function getDefaultDriver()
    {
        return 'smtp';
    }

    public function build($config)
    {
        // On-demand mailers must follow the same rule, even when callers request log/array.
        $s = $this->app->make(SmtpSettings::class)->resolved();
        $mailer = parent::build([
            'name' => 'smtp', 'transport' => 'smtp', 'scheme' => $s['scheme'],
            'host' => $s['host'], 'port' => (int) $s['port'],
            'username' => $s['username'], 'password' => $s['password'],
            'timeout' => 15, 'require_tls' => $this->app->environment('production'),
            'local_domain' => parse_url(config('app.url'), PHP_URL_HOST),
        ]);
        $mailer->alwaysFrom($s['from_address'], $s['from_name']);
        return $mailer;
    }
}
