<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'ticket_id',
    'auteur_id',
    'contenu',
    'est_solution',
])]
class Commentaire extends Model
{
    /** @use HasFactory<\Database\Factories\CommentaireFactory> */
    use HasFactory, HasUuids;

    protected function casts() : array
    {
        return [
            'est_solution' => 'boolean',
        ];
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function auteur()
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }

    // Marquer comme solution et fermer le ticket
    public function marquerCommeSolution(): void
    {
        $this->update(['est_solution' => true]);
        $this->ticket->resoudre();
    }
}
