<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketResolvedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $ticket;

    /**
     * Create a new notification instance.
     */
    public function __construct(Ticket $ticket)
    {
        $this->ticket = $ticket;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail', 'broadcast'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $url = config('app.url').'/tickets/'.$this->ticket->id;

        return (new MailMessage)
            ->subject('Ticket #'.$this->ticket->id.' résolu')
            ->line("Le ticket \"{$this->ticket->titre}\" a été marqué comme résolu.")
            ->action('Voir le ticket', $url)
            ->line('Vous pouvez consulter les détails dans l’interface.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'ticket_id' => $this->ticket->id,
            'ticket_reference' => $this->ticket->reference,
            'ticket_titre' => $this->ticket->titre,
            'titre' => $this->ticket->titre,
            'date_resolution' => $this->ticket->date_resolution?->toDateTimeString(),
            'titre_notification' => 'Ticket résolu',
            'contenu' => "Le ticket {$this->ticket->reference} « {$this->ticket->titre} » a été marqué comme résolu.",
        ];
    }
}
