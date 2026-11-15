<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class CustomerResetPassword extends ResetPassword
{
    public function toMail($notifiable)
    {
        $path = route('password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset(), 'lang' => $notifiable->preferredLocale()], false);
        return (new MailMessage)->subject(__('identity.reset_subject'))->view('mail.identity', [
            'heading' => __('identity.reset_title'), 'bodyCopy' => __('identity.reset_mail'),
            'action' => __('identity.reset_action'), 'actionUrl' => rtrim(config('app.url'), '/').$path,
            'footnote' => __('identity.reset_expiry', ['minutes' => config('auth.passwords.users.expire')]), 'locale' => app()->getLocale(),
        ]);
    }
}
