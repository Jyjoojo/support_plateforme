<?php

namespace App\Http\Resources;

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

        return [
            'id' => $this->id,
            'lu' => $this->read_at !== null,
            'cree_le' => $this->created_at?->toISOString(),
            'ticket_id' => $data['ticket_id'] ?? null,
            'ticket_reference' => $data['ticket_reference'] ?? null,
            'ticket_titre' => $data['ticket_titre'] ?? null,
            'auteur_nom' => $data['auteur_nom'] ?? 'Utilisateur',
            'excerpt' => $data['excerpt'] ?? '',
        ];
    }
}
