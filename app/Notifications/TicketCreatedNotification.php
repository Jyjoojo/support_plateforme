<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Ticket $ticket)
    {
    }

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $url = config('app.url') . '/tickets/' . $this->ticket->id;

        return (new MailMessage)
            ->subject('Nouveau ticket #' . $this->ticket->id)
            ->line("Un nouveau ticket a été créé : {$this->ticket->titre}")
            ->action('Voir le ticket', $url);
    }

    public function toArray($notifiable): array
    {
        return [
            'ticket_id' => $this->ticket->id,
            'titre'     => $this->ticket->titre,
            'priorite'  => $this->ticket->priorite,
            'createur'  => $this->ticket->createur_id,
        ];
    }
}
