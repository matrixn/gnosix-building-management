<?php

namespace App\Support;

class ContactAvailability
{
    public static function ready(): bool
    {
        return config('gnosix.contact_enabled')
            && filter_var(config('gnosix.contact_recipient'), FILTER_VALIDATE_EMAIL)
            && app(SmtpSettings::class)->ready();
    }
}
