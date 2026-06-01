<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable('libelle', 'description')]
class Categorie extends Model
{
    //
    use HasFactory, HasUuids;

    // ─── Relations ───────────────────────────────────────────────

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function articlesBase()
    {
        return $this->hasMany(ArticleBase::class);
    }
}
