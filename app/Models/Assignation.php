<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

#[Fillable([
    'ticket_id',
    'technicien_id',
    'assigne_par_id',
    'methode',
    'motif',
    'date_assignation',
])]
class Assignation extends Model
{
    /** @use HasFactory<\Database\Factories\AssignationFactory> */
    use HasFactory, HasUuids;
    
    protected function casts() : array
    {
        return [
            'date_assignation' => 'datetime',
        ];
    }

    const METHODES = ['manuelle', 'auto_assignation', 'par_specialite'];

    // ─── Relations ───────────────────────────────────────────────

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function technicien(): BelongsTo
    {
        return $this->belongsTo(Technicien::class);
    }

    public function assignePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigne_par_id');
    }

    // ─── Boot : mise à jour du compteur technicien ────────────────

    protected static function booted(): void
    {
        // Incrémenter tickets_en_cours à la création
        static::created(function (Assignation $assignation) {
            $assignation->technicien->incrementTickets();
        });

        // Décrémenter quand le ticket est résolu/fermé (géré dans TicketObserver)
        // Ici on gère la réassignation : on décrémente l'ancien technicien
        static::creating(function (Assignation $assignation) {
            $derniereAssignation = Assignation::where('ticket_id', $assignation->ticket_id)
                ->latest('date_assignation')
                ->first();

            if ($derniereAssignation) {
                $derniereAssignation->technicien->decrementTickets();
            }
        });
    }
}
