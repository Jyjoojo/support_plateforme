<?php

namespace App\Models;

use Database\Factories\CommentaireFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'ticket_id',
    'auteur_id',
    'contenu',
    'est_solution',
    'solution_validee_at',
    'solution_rejetee_at',
    'solution_validee_par_id',
])]
class Commentaire extends Model
{
    /** @use HasFactory<CommentaireFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'est_solution' => 'boolean',
            'solution_validee_at' => 'datetime',
            'solution_rejetee_at' => 'datetime',
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

    public function solutionValideePar()
    {
        return $this->belongsTo(User::class, 'solution_validee_par_id');
    }

    // Une solution proposée attend toujours la confirmation du client.
    public function marquerCommeSolution(): void
    {
        $this->update(['est_solution' => true]);
        $this->ticket->mettreEnAttente();
    }
}
