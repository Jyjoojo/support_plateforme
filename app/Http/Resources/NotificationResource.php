<?php

namespace App\Http\Resources;

use App\Notifications\SimpleNotification;
use App\Notifications\SystemMessageNotification;
use App\Notifications\TicketAssignedNotification;
use App\Notifications\TicketCommentedNotification;
use App\Notifications\TicketCreatedNotification;
use App\Notifications\TicketReminderNotification;
use App\Notifications\TicketResolvedNotification;
use App\Notifications\TicketSolutionProposedNotification;
use App\Notifications\TicketStatusChangedNotification;
use App\Support\NotificationCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = $this->data ?? [];
        $ticketId = $data['ticket_id'] ?? null;

        return [
            'id' => $this->id,
            'type' => $this->type,
            'categorie' => NotificationCatalog::categorie($this->type),
            'titre' => $data['titre_notification'] ?? $this->titre($data),
            'contenu' => $data['contenu'] ?? $this->contenu($data),
            'lu' => $this->read_at !== null,
            'lu_le' => $this->read_at?->toISOString(),
            'cree_le' => $this->created_at?->toISOString(),
            'ticket_id' => $ticketId,
            'ticket_reference' => $data['ticket_reference'] ?? null,
            'ticket_titre' => $data['ticket_titre'] ?? $data['titre'] ?? null,
            'action' => $ticketId ? [
                'type' => 'ouvrir_ticket',
                'ticket_id' => $ticketId,
            ] : null,
            'donnees' => $data,
        ];
    }

    /** @param array<string, mixed> $data */
    private function titre(array $data): string
    {
        return match ($this->type) {
            TicketAssignedNotification::class => 'Ticket assigné',
            TicketCommentedNotification::class => 'Nouveau commentaire',
            TicketCreatedNotification::class => 'Nouveau ticket',
            TicketReminderNotification::class => 'Rappel sur un ticket',
            TicketResolvedNotification::class => 'Ticket résolu',
            TicketSolutionProposedNotification::class => 'Solution proposée',
            TicketStatusChangedNotification::class => 'Statut du ticket modifié',
            SystemMessageNotification::class => $data['title'] ?? 'Message système',
            SimpleNotification::class => $this->titreSimple($data['type'] ?? null),
            default => 'Notification',
        };
    }

    /** @param array<string, mixed> $data */
    private function contenu(array $data): string
    {
        $ticket = $this->ticketLabel($data);

        return match ($this->type) {
            TicketAssignedNotification::class => "Le ticket {$ticket} vous a été assigné.",
            TicketCommentedNotification::class => $this->contenuCommentaire($data, $ticket),
            TicketCreatedNotification::class => "Un nouveau ticket a été créé : {$ticket}.",
            TicketReminderNotification::class => $this->contenuRappel($data, $ticket),
            TicketResolvedNotification::class => "Le ticket {$ticket} a été marqué comme résolu.",
            TicketSolutionProposedNotification::class => "Une solution a été proposée pour le ticket {$ticket}.",
            TicketStatusChangedNotification::class => sprintf(
                'Le statut du ticket %s est passé de « %s » à « %s ».',
                $ticket,
                $this->formatStatut($data['ancien_statut'] ?? null),
                $this->formatStatut($data['nouveau_statut'] ?? null),
            ),
            SystemMessageNotification::class => $data['message'] ?? '',
            SimpleNotification::class => $data['message'] ?? '',
            default => $data['message'] ?? '',
        };
    }

    /** @param array<string, mixed> $data */
    private function ticketLabel(array $data): string
    {
        $reference = $data['ticket_reference'] ?? null;
        $titre = $data['ticket_titre'] ?? $data['titre'] ?? null;

        if ($reference && $titre) {
            return "{$reference} « {$titre} »";
        }

        if ($reference) {
            return (string) $reference;
        }

        if ($titre) {
            return "« {$titre} »";
        }

        return isset($data['ticket_id']) ? '#'.$data['ticket_id'] : '';
    }

    /** @param array<string, mixed> $data */
    private function contenuCommentaire(array $data, string $ticket): string
    {
        $auteur = $data['auteur_nom'] ?? 'Un utilisateur';
        $excerpt = trim((string) ($data['excerpt'] ?? ''));
        $contenu = "{$auteur} a ajouté un commentaire sur le ticket {$ticket}.";

        return $excerpt === '' ? $contenu : $contenu." « {$excerpt} »";
    }

    /** @param array<string, mixed> $data */
    private function contenuRappel(array $data, string $ticket): string
    {
        $contenu = "Le ticket {$ticket} nécessite votre attention.";

        if (! empty($data['deadline'])) {
            $contenu .= ' Date limite : '.$data['deadline'].'.';
        }

        if (! empty($data['reason'])) {
            $contenu .= ' Motif : '.$data['reason'].'.';
        }

        return $contenu;
    }

    private function formatStatut(mixed $statut): string
    {
        return str_replace('_', ' ', (string) ($statut ?? 'inconnu'));
    }

    private function titreSimple(mixed $type): string
    {
        return match ($type) {
            'nouveau_ticket' => 'Nouveau ticket',
            'ticket_assigne' => 'Ticket assigné',
            'nouveau_commentaire' => 'Nouveau commentaire',
            'statut_change' => 'Statut du ticket modifié',
            'ticket_resolu' => 'Ticket résolu',
            'rappel' => 'Rappel sur un ticket',
            default => 'Notification',
        };
    }
}
