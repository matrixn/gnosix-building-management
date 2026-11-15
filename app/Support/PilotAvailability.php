<?php

namespace App\Support;

class PilotAvailability
{
    public static function ready(): bool
    {
        return config('gnosix.pilot_enabled')
            && filter_var(config('gnosix.pilot_recipient'), FILTER_VALIDATE_EMAIL)
            && app(SmtpSettings::class)->ready();
    }
}
