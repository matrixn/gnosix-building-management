<?php

namespace App\Notifications;

use App\Models\Organization;
use App\Support\SiteSettings;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OperationsDigest extends Notification
{
    public function __construct(private Organization $organization, private array $counts) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->mailer('smtp')->subject(__('ops.digest_subject'))->greeting(__('ops.digest_subject'))
            ->line(__('ops.digest_intro', ['organization' => $this->organization->name]));
        foreach ($this->counts as $key => $value) {
            $mail->line(__('ops.'.$key).' · '.$value);
        }

        return $mail->action(__('ops.open_center'), rtrim(SiteSettings::value('canonical_url'), '/').route('app.operations.index', $this->organization, absolute: false));
    }
}
