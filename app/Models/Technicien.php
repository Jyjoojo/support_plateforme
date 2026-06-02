<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'specialite', 'tickets_en_cours',])]
class Technicien extends Model
{
    //
    use HasUuids, HasFactory;
    
    protected function casts(): array
    {
        return [
            'tickets_en_cours' => 'integer',
        ];
    }

    // ─── Relations ───────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignations()
    {
        return $this->hasMany(Assignation::class);
    }

    public function articlesBase()
    {
        return $this->hasMany(ArticleBase::class);
    }

    // Tickets actuellement assignés à ce technicien (via la dernière assignation active)
    public function ticketsAssignes()
    {
        return $this->hasManyThrough(Ticket::class, Assignation::class, 'technicien_id', 'id', 'id', 'ticket_id');
    }

    // ─── Helpers ─────────────────────────────────────────────────

    public function getNomCompletAttribute(): string
    {
        return $this->user->prenom . ' ' . $this->user->nom;
    }

    public function incrementTickets(): void
    {
        $this->increment('tickets_en_cours');
    }

    public function decrementTickets(): void
    {
        if ($this->tickets_en_cours > 0) {
            $this->decrement('tickets_en_cours');
        }
    }
}
