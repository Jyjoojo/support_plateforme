<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'entreprise', 'secteur', 'est_client_officiel',])]
class Client extends Model
{
    //
    protected function casts() : array
    {
        return [
            'est_client_officiel' => 'boolean',
        ];
    }
}
