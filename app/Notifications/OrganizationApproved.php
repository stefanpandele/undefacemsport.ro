<?php

namespace App\Notifications;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The account is open — here is how to get into it.
 *
 * Queued because it is sent from an admin clicking a button, and a slow mail
 * server has no business holding that click.
 */
class OrganizationApproved extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string|null  $passwordToken  set only when the account was created
     *                                      by this approval, so somebody who
     *                                      already had a password is not told to
     *                                      make a new one
     */
    public function __construct(
        public Organization $organization,
        public ?string $passwordToken = null,
    ) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Contul „'.$this->organization->name.'" a fost aprobat')
            ->greeting('Bun venit!')
            ->line('Cererea pentru „'.$this->organization->name.'" a fost aprobată și contul e deschis.');

        if ($this->passwordToken !== null) {
            $message
                ->line('Îți alegi parola de aici, apoi intri în cont:')
                ->action('Alege-ți parola', route('password.reset', [
                    'token' => $this->passwordToken,
                    'email' => $notifiable->getEmailForPasswordReset(),
                ]));
        } else {
            $message
                ->line('Intri cu contul pe care îl ai deja:')
                ->action('Intră în cont', url('/cont'));
        }

        // Said plainly, because it is the first thing they will wonder: an
        // organization with nothing published has no public page yet.
        return $message->line(
            'Pagina publică apare după ce publici prima ofertă — cursuri, un spațiu sau un serviciu. '
            .'Le găsești pe toate trei pe prima pagină din cont.'
        );
    }
}
