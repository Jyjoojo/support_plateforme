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
            'id' => $this->id,
            'contenu' => $this->contenu,
            'est_solution' => $this->est_solution,
            'solution_validee_at' => $this->solution_validee_at?->toDateTimeString(),
            'solution_rejetee_at' => $this->solution_rejetee_at?->toDateTimeString(),
            'solution_validee_par_id' => $this->solution_validee_par_id,
            'created_at' => $this->created_at?->toDateTimeString(),
            'auteur' => $this->whenLoaded('auteur', fn () => [
                'id' => $this->auteur->id,
                'nom_complet' => $this->auteur->prenom.' '.$this->auteur->nom,
                'role' => $this->auteur->role,
            ]),
        ];
    }
}
