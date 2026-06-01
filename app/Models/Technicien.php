<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'specialite', 'tickets_en_cours',])]
class Technicien extends Model
{
    //
    use HasUuids;
    
    protected function casts(): array
    {
        return [
            'tickets_en_cours' => 'integer',
        ];
    }
}
