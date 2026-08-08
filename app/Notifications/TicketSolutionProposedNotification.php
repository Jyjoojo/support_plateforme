<?php

namespace App\Notifications;

use App\Models\Commentaire;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketSolutionProposedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private Ticket $ticket, private Commentaire $commentaire) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Solution proposée pour le ticket {$this->ticket->reference}")
            ->line("Une solution a été proposée pour « {$this->ticket->titre} ».")
            ->line('Merci de la tester, puis de confirmer la résolution ou de signaler que le problème persiste.')
            ->action('Vérifier la solution', config('app.url').'/tickets/'.$this->ticket->id);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'ticket_id' => $this->ticket->id,
            'ticket_reference' => $this->ticket->reference,
            'ticket_titre' => $this->ticket->titre,
            'commentaire_id' => $this->commentaire->id,
            'titre' => $this->ticket->titre,
            'titre_notification' => 'Solution proposée',
            'contenu' => "Une solution a été proposée pour le ticket {$this->ticket->reference} « {$this->ticket->titre} ».",
        ];
    }
}
