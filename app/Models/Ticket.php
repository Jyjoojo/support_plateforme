<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

#[Fillable(['titre', 'description', 'statut', 'priorite', 'source_creation', 'categorie_id', 'createur_id', 'createur_type', 'client_id', 'date_resolution'])]
class Ticket extends Model
{
    use HasFactory, HasUuids;

    protected $appends = ['reference'];

    protected static function booted(): void
    {
        static::created(function (Ticket $ticket): void {
            // Le trigger PostgreSQL renseigne la référence pendant l'insertion.
            $ticket->refresh();
        });

        static::deleting(function (Ticket $ticket): void {
            Storage::disk(config('filesystems.attachments_disk', 'private'))
                ->deleteDirectory("tickets/{$ticket->id}");
        });
    }

    // Définir les valeurs par défaut des attributs lors de l'instanciation du modèle
    protected $attributes = [
        'statut' => 'nouveau',
        'priorite' => 'normale',
    ];

    protected function casts(): array
    {
        return [
            'annee' => 'integer',
            'numero' => 'integer',
            'date_resolution' => 'datetime',
        ];
    }

    protected function reference(): Attribute
    {
        return Attribute::get(
            fn () => sprintf('TK%02d-%04d', $this->annee % 100, $this->numero)
        );
    }

    // Valeurs possibles des enums (utile pour la validation)
    const STATUTS = ['nouveau', 'en_cours', 'en_attente', 'resolu', 'ferme'];

    const PRIORITES = ['basse', 'normale', 'haute', 'urgente'];

    const SOURCES = ['client', 'technicien', 'administrateur'];

    private const TRANSITIONS_STATUT = [
        'nouveau' => ['en_cours'],
        'en_cours' => ['en_attente'],
        'en_attente' => ['en_cours', 'resolu'],
        'resolu' => ['ferme', 'en_cours'],
        'ferme' => ['en_cours'],
    ];

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
        $relatedTable = (new Assignation)->getTable();

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
        return ! in_array($this->statut, ['resolu', 'ferme']);
    }

    public function peutTransitionnerVers(string $nouveauStatut): bool
    {
        return in_array(
            $nouveauStatut,
            self::TRANSITIONS_STATUT[$this->statut] ?? [],
            true
        );
    }

    public function fermer(): void
    {
        if (! $this->peutTransitionnerVers('ferme')) {
            throw new \DomainException('Le ticket doit être résolu avant sa fermeture.');
        }

        $this->update(['statut' => 'ferme']);
    }

    public function mettreEnAttente(): void
    {
        if ($this->statut !== 'en_cours') {
            throw new \DomainException('Seul un ticket en cours peut être mis en attente.');
        }

        $this->update(['statut' => 'en_attente', 'date_resolution' => null]);
    }

    public function reprendre(): void
    {
        if ($this->statut !== 'en_attente') {
            throw new \DomainException('Seul un ticket en attente peut être repris.');
        }

        $this->update(['statut' => 'en_cours', 'date_resolution' => null]);
    }

    public function resoudre(): void
    {
        if ($this->statut !== 'en_attente') {
            throw new \DomainException('Le ticket doit être en attente de validation.');
        }

        $this->update([
            'statut' => 'resolu',
            'date_resolution' => now(),
        ]);
    }

    public function rouvrir(): void
    {
        if (! in_array($this->statut, ['resolu', 'ferme'], true)) {
            throw new \DomainException('Seul un ticket résolu ou fermé peut être rouvert.');
        }

        $this->update(['statut' => 'en_cours', 'date_resolution' => null]);
    }
}
