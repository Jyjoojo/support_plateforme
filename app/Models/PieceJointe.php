<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'ticket_id',
    'chemin_fichier',
    'nom_fichier',
    'taille',
    'type_mime',
    'date_upload',
])]
class PieceJointe extends Model
{
    /** @use HasFactory<\Database\Factories\PieceJointeFactory> */
    use HasFactory, HasUuids;

    protected function casts()
    {
        return [
            'date_upload' => 'datetime',
            'taille'      => 'integer',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    // Taille lisible (ex: "2.4 MB")
    public function getTailleListableAttribute(): string
    {
        $kb = $this->taille / 1024;
        if ($kb < 1024) return round($kb, 1) . ' KB';
        return round($kb / 1024, 1) . ' MB';
    }

}
