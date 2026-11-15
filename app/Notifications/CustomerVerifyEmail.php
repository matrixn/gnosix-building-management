<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;

class CustomerVerifyEmail extends VerifyEmail
{
    protected function verificationUrl($notifiable)
    {
        $path = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $notifiable->getKey(), 'hash' => sha1($notifiable->getEmailForVerification()), 'lang' => $notifiable->preferredLocale(),
        ], absolute: false);
        return rtrim(config('app.url'), '/').$path;
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)->subject(__('identity.verify_subject'))->view('mail.identity', [
            'heading' => __('identity.verify_title'), 'bodyCopy' => __('identity.verify_mail'),
            'action' => __('identity.verify_action'), 'actionUrl' => $this->verificationUrl($notifiable),
            'footnote' => __('identity.verify_expiry'), 'locale' => app()->getLocale(),
        ]);
    }
}
