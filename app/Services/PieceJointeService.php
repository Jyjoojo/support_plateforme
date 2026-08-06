<?php

namespace App\Services;

use App\Models\PieceJointe;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\UploadedFile;

class PieceJointeService
{
    public function enregistrer(Ticket $ticket, UploadedFile $fichier, User $auteur): PieceJointe
    {
        $disque = config('filesystems.attachments_disk', 'private');
        $chemin = $fichier->store("tickets/{$ticket->id}", $disque);

        return PieceJointe::create([
            'ticket_id' => $ticket->id,
            'ajoute_par_id' => $auteur->id,
            'nom_fichier' => $fichier->getClientOriginalName(),
            'chemin_fichier' => $chemin,
            'type_mime' => $fichier->getMimeType() ?: $fichier->getClientMimeType(),
            'taille' => $fichier->getSize(),
        ]);
    }
}
