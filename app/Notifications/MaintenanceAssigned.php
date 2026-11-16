<?php

namespace App\Notifications;

use App\Models\WorkOrder;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MaintenanceAssigned extends Notification
{
    public function __construct(public WorkOrder $work) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('GNOSIX · '.$this->work->reference)->line($this->work->title)->action('GNOSIX', route('app.maintenance.work.show', [$this->work->organization, $this->work->uuid]));
    }
}
