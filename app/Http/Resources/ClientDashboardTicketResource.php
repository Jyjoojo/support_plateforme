<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientDashboardTicketResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'titre' => $this->titre,
            'categorie' => $this->whenLoaded('categorie', fn () => $this->categorie ? [
                'id' => $this->categorie->id,
                'libelle' => $this->categorie->libelle,
            ] : null),
            'statut' => $this->statut,
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
