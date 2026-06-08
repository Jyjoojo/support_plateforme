<?php

namespace App\Notifications;

use App\Models\Commentaire;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketCommentedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Ticket $ticket, public Commentaire $commentaire)
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
            ->subject('Nouveau commentaire sur le ticket #' . $this->ticket->id)
            ->line("{$this->commentaire->auteur->prenom} a ajouté un commentaire :\n" . substr($this->commentaire->contenu, 0, 200))
            ->action('Voir le ticket', $url);
    }

    public function toArray($notifiable): array
    {
        return [
            'ticket_id' => $this->ticket->id,
            'commentaire_id' => $this->commentaire->id,
            'auteur_id' => $this->commentaire->auteur_id,
            'excerpt' => substr($this->commentaire->contenu, 0, 200),
        ];
    }
}
