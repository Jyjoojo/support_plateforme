<?php

namespace App\Support;

use App\Notifications\SimpleNotification;
use App\Notifications\TicketAssignedNotification;
use App\Notifications\TicketCommentedNotification;
use App\Notifications\TicketCreatedNotification;
use App\Notifications\TicketReminderNotification;
use App\Notifications\TicketResolvedNotification;
use App\Notifications\TicketSolutionProposedNotification;
use App\Notifications\TicketStatusChangedNotification;

final class NotificationCatalog
{
    public const TICKET = 'ticket';

    public const SYSTEME = 'systeme';

    /** @return list<class-string> */
    public static function ticketTypes(): array
    {
        return [
            TicketAssignedNotification::class,
            TicketCommentedNotification::class,
            TicketCreatedNotification::class,
            TicketReminderNotification::class,
            TicketResolvedNotification::class,
            TicketSolutionProposedNotification::class,
            TicketStatusChangedNotification::class,
            SimpleNotification::class,
        ];
    }

    public static function categorie(string $type): string
    {
        return in_array($type, self::ticketTypes(), true)
            ? self::TICKET
            : self::SYSTEME;
    }

    /** @return list<array{valeur: string, libelle: string}> */
    public static function categories(): array
    {
        return [
            ['valeur' => self::TICKET, 'libelle' => 'Tickets'],
            ['valeur' => self::SYSTEME, 'libelle' => 'Système'],
        ];
    }
}
