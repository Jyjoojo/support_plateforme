<?php

namespace App\Models;

use Database\Factories\ArticleBaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'technicien_id',
    'auteur_id',
    'categorie_id',
    'titre',
    'contenu',
    'mots_cles',
    'vues',
    'publie',
    'ticket_id',
    'commentaire_solution_id',
    'statut_editorial',
    'soumis_at',
    'soumis_par_id',
    'valide_at',
    'valide_par_id',
    'motif_refus',
    'publie_at',
    'archive_at',
    'archive_par_id',
])]
class ArticleBase extends Model
{
    /** @use HasFactory<ArticleBaseFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $appends = ['reference'];

    public const STATUT_BROUILLON = 'brouillon';

    public const STATUT_EN_ATTENTE = 'en_attente_validation';

    public const STATUT_A_CORRIGER = 'a_corriger';

    public const STATUT_PUBLIE = 'publie';

    public const STATUT_ARCHIVE = 'archive';

    protected function casts(): array
    {
        return [
            'publie' => 'boolean',
            'vues' => 'integer',
            'soumis_at' => 'datetime',
            'valide_at' => 'datetime',
            'publie_at' => 'datetime',
            'archive_at' => 'datetime',
            'annee' => 'integer',
            'numero' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ArticleBase $article): void {
            if (! $article->statut_editorial) {
                $article->statut_editorial = $article->publie
                    ? self::STATUT_PUBLIE
                    : self::STATUT_BROUILLON;
            }

            if (! $article->auteur_id && $article->technicien_id) {
                $article->auteur_id = Technicien::query()->find($article->technicien_id)?->user_id;
            }

            if ($article->statut_editorial === self::STATUT_PUBLIE) {
                $article->publie = true;
                $article->publie_at ??= now();
            }
        });

        static::created(function (ArticleBase $article): void {
            // Le trigger PostgreSQL renseigne la référence pendant l'insertion.
            $article->refresh();
        });
    }

    protected function reference(): Attribute
    {
        return Attribute::get(
            fn () => sprintf('KB-%04d-%04d', $this->annee, $this->numero)
        );
    }

    // ─── Relations ───────────────────────────────────────────────

    public function technicien(): BelongsTo
    {
        return $this->belongsTo(Technicien::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }

    public function soumisPar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'soumis_par_id');
    }

    public function validePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par_id');
    }

    public function archivePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archive_par_id');
    }

    public function categorie(): BelongsTo
    {
        return $this->belongsTo(Categorie::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function commentaireSolution(): BelongsTo
    {
        return $this->belongsTo(Commentaire::class, 'commentaire_solution_id');
    }

    // ─── Scopes ──────────────────────────────────────────────────
    public function scopePublies(Builder $query)
    {
        $query->where('statut_editorial', self::STATUT_PUBLIE)->where('publie', true);
    }

    public function scopeRecherche(Builder $query, string $terme)
    {
        return $query->whereFullText(['titre', 'contenu', 'mots_cles'], $terme);
    }

    // ─── Helpers ─────────────────────────────────────────────────

    public function incrementerVues(): void
    {
        $this->increment('vues');
    }

    public function estEditableParTechnicien(): bool
    {
        return in_array($this->statut_editorial, [self::STATUT_BROUILLON, self::STATUT_A_CORRIGER], true);
    }

    public function soumettre(User $user): void
    {
        $this->update([
            'statut_editorial' => self::STATUT_EN_ATTENTE,
            'publie' => false,
            'soumis_at' => now(),
            'soumis_par_id' => $user->id,
            'motif_refus' => null,
        ]);
    }

    public function publier(User $user): void
    {
        $this->update([
            'statut_editorial' => self::STATUT_PUBLIE,
            'publie' => true,
            'valide_at' => now(),
            'valide_par_id' => $user->id,
            'publie_at' => now(),
            'motif_refus' => null,
        ]);
    }

    public function refuser(User $user, string $motif): void
    {
        $this->update([
            'statut_editorial' => self::STATUT_A_CORRIGER,
            'publie' => false,
            'valide_at' => now(),
            'valide_par_id' => $user->id,
            'motif_refus' => $motif,
        ]);
    }

    public function depublier(): void
    {
        $this->update([
            'statut_editorial' => self::STATUT_BROUILLON,
            'publie' => false,
            'publie_at' => null,
            'valide_at' => null,
            'valide_par_id' => null,
        ]);
    }

    public function archiver(?User $user = null): void
    {
        $this->update([
            'statut_editorial' => self::STATUT_ARCHIVE,
            'publie' => false,
            'archive_at' => now(),
            'archive_par_id' => $user?->id,
        ]);
        $this->delete();
    }

    public function restaurer(): void
    {
        $this->restore();
        $this->update([
            'statut_editorial' => self::STATUT_BROUILLON,
            'publie' => false,
            'archive_at' => null,
            'archive_par_id' => null,
            'soumis_at' => null,
            'soumis_par_id' => null,
            'valide_at' => null,
            'valide_par_id' => null,
            'motif_refus' => null,
            'publie_at' => null,
        ]);
    }

    public function getMotsClesArrayAttribute(): array
    {
        if (! $this->mots_cles) {
            return [];
        }

        return array_map('trim', explode(',', $this->mots_cles));
    }
}
