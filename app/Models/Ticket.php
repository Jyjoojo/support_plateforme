<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Ticket extends Model
{
    use HasUuids;

    protected $fillable = [
        'titre', 'description', 'statut', 'priorité','source_creation', 'categorie_id', 'createur_id', 'client_concerne_id'

    ];
}
