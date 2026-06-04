<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentaireResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'contenu'      => $this->contenu,
            'est_solution' => $this->est_solution,
            'created_at'   => $this->created_at?->toDateTimeString(),
            'auteur' => $this->whenLoaded('auteur', fn() => [
                'id'          => $this->auteur->id,
                'nom_complet' => $this->auteur->prenom . ' ' . $this->auteur->nom,
                'role'        => $this->auteur->role,
            ]),
        ];
    }
}
