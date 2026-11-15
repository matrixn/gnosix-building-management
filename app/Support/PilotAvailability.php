<?php

namespace App\Support;

class PilotAvailability
{
    public static function ready(): bool
    {
        $mailer = config('mail.default');
        $transport = config("mail.mailers.{$mailer}.transport");

        return config('gnosix.pilot_enabled')
            && filter_var(config('gnosix.pilot_recipient'), FILTER_VALIDATE_EMAIL)
            && filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL)
            && in_array($transport, ['smtp', 'sendmail', 'ses', 'ses-v2', 'postmark', 'resend'], true);
    }
}
