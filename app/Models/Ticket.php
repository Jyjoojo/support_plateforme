<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    //
    protected $fillable = [
        'titre', 'description', 'statut', 'priorité','source_creation', 'categorie_id', 'createur_id', 'client_concerne_id'

    ];
}
