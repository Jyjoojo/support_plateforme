<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

#[Fillable(['titre', 'description', 'statut', 'priorite', 'source_creation', 'categorie_id', 'createur_id', 'createur_type', 'client_id', 'date_resolution'])]
class Ticket extends Model
{
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'date_resolution' => 'datetime',
        ];
    }

    // Valeurs possibles des enums (utile pour la validation)
    const STATUTS = ['nouveau', 'en_cours', 'en_attente', 'resolu', 'ferme'];
    const PRIORITES = ['basse', 'normale', 'haute', 'urgente'];
    const SOURCES = ['client', 'technicien', 'administrateur'];

    // ─── Relations ───────────────────────────────────────────────

    // Relation polymorphe : le créateur peut être Client, Technicien ou Admin (via User)
    public function createur()
    {
        return $this->morphTo();
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function categorie()
    {
        return $this->belongsTo(Categorie::class);
    }

    public function assignations()
    {
        return $this->hasMany(Assignation::class);
    }

    // Dernière assignation active
    public function assignationActive()
    {
        $relatedTable = (new Assignation())->getTable();

        return $this->hasOne(Assignation::class)
            ->whereRaw("{$relatedTable}.id = (
                select id
                from {$relatedTable} as latest_assignation
                where latest_assignation.ticket_id = {$relatedTable}.ticket_id
                order by latest_assignation.date_assignation desc, latest_assignation.created_at desc
                limit 1
            )");
    }

    // Technicien actuellement assigné
    public function technicienAssigne()
    {
        return $this->hasOneThrough(
            Technicien::class,
            Assignation::class,
            'ticket_id',
            'id',
            'id',
            'technicien_id'
        );
    }

    public function commentaires()
    {
        return $this->hasMany(Commentaire::class);
    }

    public function piecesJointes()
    {
        return $this->hasMany(PieceJointe::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    // ─── Scopes ──────────────────────────────────────────────────
    #[Scope]
    public function ouverts(Builder $query)
    {
        $query->whereNotIn('statut', ['resolu', 'ferme']);
    }

    #[Scope]
    public function priorite(Builder $query, string $priorite)
    {
        $query->where('priorite', $priorite);
    }

    #[Scope]
    public function parCategorie(Builder $query, string $categorieId)
    {
        $query->where('categorie_id', $categorieId);
    }

    // ─── Helpers ─────────────────────────────────────────────────

    public function estOuvert(): bool
    {
        return !in_array($this->statut, ['resolu', 'ferme']);
    }

    public function fermer(): void
    {
        $this->update(['statut' => 'ferme']);
    }

    public function resoudre(): void
    {
        $this->update([
            'statut'          => 'resolu',
            'date_resolution' => now(),
        ]);
    }
}
