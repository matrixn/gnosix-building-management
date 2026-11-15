<?php

namespace App\Support;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Schema;

class SmtpSettings
{
    public function saved(): ?array
    {
        if (! Schema::hasTable('platform_settings')) {
            return null;
        }
        return PlatformSetting::find('smtp')?->value;
    }

    public function resolved(): array
    {
        $saved = $this->saved();
        if ($saved !== null) {
            return $saved;
        }
        return [
            'host' => config('mail.mailers.smtp.host'),
            'port' => config('mail.mailers.smtp.port', 465),
            'scheme' => config('mail.mailers.smtp.scheme') ?: ((int) config('mail.mailers.smtp.port') === 465 ? 'smtps' : 'smtp'),
            'username' => config('mail.mailers.smtp.username'),
            'password' => config('mail.mailers.smtp.password'),
            'from_address' => config('mail.from.address'),
            'from_name' => config('mail.from.name', 'GNOSIX Building Management'),
        ];
    }

    public function ready(): bool
    {
        $s = $this->resolved();
        return filled($s['host']) && filter_var($s['from_address'], FILTER_VALIDATE_EMAIL)
            && in_array($s['scheme'], ['smtp', 'smtps'], true) && (int) $s['port'] > 0;
    }
}
