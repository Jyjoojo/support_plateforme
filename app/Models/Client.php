<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'entreprise', 'secteur', 'est_client_officiel',])]
class Client extends Model
{
    //
    use HasFactory, HasUuids;
    protected function casts() : array
    {
        return [
            'est_client_officiel' => 'boolean',
        ];
    }

    // ─── Relations ───────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function statistiques()
    {
        return $this->hasMany(Statistique::class, 'filtre_client_id');
    }

    // ─── Helpers ─────────────────────────────────────────────────

    public function getNomCompletAttribute(): string
    {
        return $this->user->prenom . ' ' . $this->user->nom;
    }
}
