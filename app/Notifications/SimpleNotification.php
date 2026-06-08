<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SimpleNotification extends Notification
{
    use Queueable;

    public function __construct(public string $message, public string $type, public ?string $ticketId = null)
    {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'message' => $this->message,
            'type' => $this->type,
            'ticket_id' => $this->ticketId,
        ];
    }
}
