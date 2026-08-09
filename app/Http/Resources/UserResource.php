<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'email' => $this->email,
            'role' => $this->role,
            'telephone' => $this->telephone,
            'actif' => $this->actif,
            'created_at' => $this->created_at?->toDateTimeString(),

            // Données spécifiques au rôle
            'technicien' => $this->when($this->isTechnicien(), fn () => [
                'id' => $this->technicien?->id,
                'specialite' => $this->technicien?->specialite,
                'tickets_en_cours' => $this->technicien?->tickets_en_cours,
            ]),
            'client' => $this->when($this->isClient(), fn () => [
                'id' => $this->client?->id,
                'entreprise' => $this->client?->entreprise,
                'secteur' => $this->client?->secteur,
                'adresse' => $this->client?->adresse,
                'pays' => $this->client?->pays,
                'est_client_officiel' => $this->client?->est_client_officiel,
            ]),
        ];
    }
}
