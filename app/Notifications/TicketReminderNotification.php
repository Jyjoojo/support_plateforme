<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected Ticket $ticket;
    protected string $deadline;
    protected ?string $reason;

    /**
     * Create a new notification instance.
     */
    public function __construct(Ticket $ticket, string $deadline, ?string $reason = null)
    {
        $this->ticket = $ticket;
        $this->deadline = $deadline;
        $this->reason = $reason;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $url = config('app.url') . '/tickets/' . $this->ticket->id;

        $mail = (new MailMessage)
            ->subject('Rappel : ticket #' . $this->ticket->id)
            ->line("Le ticket \"{$this->ticket->titre}\" nécessite votre attention.")
            ->line("Date limite : {$this->deadline}");

        if ($this->reason) {
            $mail->line("Motif : {$this->reason}");
        }

        return $mail->action('Voir le ticket', $url);
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
            'titre' => $this->ticket->titre,
            'deadline' => $this->deadline,
            'reason' => $this->reason,
        ];
    }
}
