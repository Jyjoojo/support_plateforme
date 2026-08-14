<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleBaseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $article = parent::toArray($request);

        if ($this->relationLoaded('auteur')) {
            $article['auteur'] = $this->auteur ? [
                'id' => $this->auteur->id,
                'nom_complet' => trim("{$this->auteur->prenom} {$this->auteur->nom}"),
                'email' => $this->auteur->email,
                'role' => $this->auteur->role,
            ] : null;
        }

        foreach (['soumisPar' => 'soumis_par', 'validePar' => 'valide_par', 'archivePar' => 'archive_par'] as $relation => $cle) {
            if ($this->relationLoaded($relation)) {
                $utilisateur = $this->{$relation};
                $article[$cle] = $utilisateur ? [
                    'id' => $utilisateur->id,
                    'nom_complet' => trim("{$utilisateur->prenom} {$utilisateur->nom}"),
                    'role' => $utilisateur->role,
                ] : null;
            }
        }

        if ($this->relationLoaded('ticket')) {
            $article['ticket'] = $this->ticket ? [
                'id' => $this->ticket->id,
                'reference' => $this->ticket->reference,
                'titre' => $this->ticket->titre,
                'statut' => $this->ticket->statut,
                'date_resolution' => $this->ticket->date_resolution?->toDateTimeString(),
                'client_id' => $this->ticket->client_id,
            ] : null;
        }

        return $article;
    }
}
