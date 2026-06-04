<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'technicien_id',
    'categorie_id',
    'titre',
    'contenu',
    'mots_cles',
    'vues',
    'publie',
])]
class ArticleBase extends Model
{
    /** @use HasFactory<\Database\Factories\ArticleBaseFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'publie' => 'boolean',
            'vues'   => 'integer',
        ];
    }

    // ─── Relations ───────────────────────────────────────────────

    public function technicien(): BelongsTo
    {
        return $this->belongsTo(Technicien::class);
    }

    public function categorie(): BelongsTo
    {
        return $this->belongsTo(Categorie::class);
    }

    // ─── Scopes ──────────────────────────────────────────────────
    public function scopePublies(Builder $query)
    {
        $query->where('publie', true);
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

    public function publier(): void
    {
        $this->update(['publie' => true]);
    }

    public function archiver(): void
    {
        $this->update(['publie' => false]);
        $this->delete(); // soft delete
    }

    public function getMotsClesArrayAttribute(): array
    {
        if (!$this->mots_cles) return [];
        return array_map('trim', explode(',', $this->mots_cles));
    }
}
