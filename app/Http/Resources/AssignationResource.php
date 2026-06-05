<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssignationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'methode'          => $this->methode,
            'motif'            => $this->motif,
            'date_assignation' => $this->date_assignation?->toDateTimeString(),
            'technicien' => $this->whenLoaded('technicien', fn() => [
                'id'          => $this->technicien->id,
                'nom_complet' => $this->technicien->nom_complet,
                'specialite'  => $this->technicien->specialite,
            ]),
            'assigne_par' => $this->whenLoaded('assignePar', fn() =>
                $this->assignePar ? [
                    'id'          => $this->assignePar->id,
                    'nom_complet' => $this->assignePar->prenom . ' ' . $this->assignePar->nom,
                    'role'        => $this->assignePar->role,
                ] : null
            ),
        ];
    }
}
