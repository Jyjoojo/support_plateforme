<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PieceJointeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom_fichier' => $this->nom_fichier,
            'type_mime' => $this->type_mime,
            'taille' => $this->taille,
            'taille_lisible' => $this->taille_listable,
            'date_upload' => $this->date_upload?->toDateTimeString(),
            'url_affichage' => route('pieces-jointes.afficher', $this->resource),
            'url_telechargement' => route('pieces-jointes.telecharger', $this->resource),
        ];
    }
}
