<?php

namespace App\Notifications;

use App\Models\Assignation;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Ticket $ticket, public Assignation $assignation)
    {
    }

    public function via($notifiable): array
    {
        return ['database', 'mail', 'broadcast'];
    }

    public function toMail($notifiable): MailMessage
    {
        $url = config('app.url') . '/tickets/' . $this->ticket->id;

        return (new MailMessage)
            ->subject('Ticket assigné #' . $this->ticket->id)
            ->line("Le ticket \"{$this->ticket->titre}\" vous a été assigné.")
            ->action('Voir le ticket', $url);
    }

    public function toArray($notifiable): array
    {
        return [
            'ticket_id' => $this->ticket->id,
            'assignation_id' => $this->assignation->id,
            'assigner_id' => $this->assignation->assigne_par_id,
        ];
    }
}
