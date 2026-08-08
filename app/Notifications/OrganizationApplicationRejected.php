<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The request was refused, and why.
 *
 * The reason is required at the point of rejection precisely so it can be sent:
 * "respinsă" on its own tells somebody who filled in a form nothing about what
 * to fix, and a reason nobody reads is a reason nobody wrote.
 */
class OrganizationApplicationRejected extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $organizationName,
        public string $reason,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Cererea pentru „'.$this->organizationName.'" nu a fost aprobată')
            ->greeting('Salut!')
            ->line('Am verificat cererea pentru „'.$this->organizationName.'" și nu am putut să o aprobăm.')
            ->line('Motivul:')
            ->line($this->reason)
            ->line('Dacă e ceva de corectat, poți trimite o cerere nouă cu datele actualizate.')
            ->action('Trimite o cerere nouă', route('organization-application.create'));
    }
}
