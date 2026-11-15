<?php

namespace App\Support;

class ContactAvailability
{
    public static function ready(): bool
    {
        return \App\Support\SiteSettings::value('contact_enabled')
            && filter_var(\App\Support\SiteSettings::value('contact_recipient'), FILTER_VALIDATE_EMAIL)
            && app(SmtpSettings::class)->ready();
    }
}
