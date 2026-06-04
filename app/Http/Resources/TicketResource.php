<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'titre'           => $this->titre,
            'description'     => $this->description,
            'statut'          => $this->statut,
            'priorite'        => $this->priorite,
            'source_creation' => $this->source_creation,
            'date_resolution' => $this->date_resolution?->toDateTimeString(),
            'created_at'      => $this->created_at?->toDateTimeString(),
            'updated_at'      => $this->updated_at?->toDateTimeString(),

            // Relations chargées conditionnellement
            'categorie'  => $this->whenLoaded('categorie', fn() => [
                'id'      => $this->categorie->id,
                'libelle' => $this->categorie->libelle,
            ]),
            'client' => $this->whenLoaded('client', fn() => [
                'id'         => $this->client->id,
                'nom_complet'=> $this->client->nom_complet,
                'entreprise' => $this->client->entreprise,
            ]),
            'technicien_assigne' => $this->whenLoaded('assignationActive', fn() =>
                $this->assignationActive ? [
                    'id'          => $this->assignationActive->technicien->id,
                    'nom_complet' => $this->assignationActive->technicien->nom_complet,
                    'specialite'  => $this->assignationActive->technicien->specialite,
                ] : null
            ),
            'commentaires'   => CommentaireResource::collection($this->whenLoaded('commentaires')),
            'pieces_jointes' => $this->whenLoaded('piecesJointes'),
        ];
    }
}
