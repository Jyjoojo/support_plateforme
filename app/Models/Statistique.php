<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'genere_par_id',
    'total_tickets',
    'tickets_resolus',
    'tickets_en_cours',
    'temps_moyen_resolution',
    'periode_debut',
    'periode_fin',
    'filtre_client_id',
    'filtre_categorie_id',
])]
class Statistique extends Model
{
    /** @use HasFactory<\Database\Factories\StatistiqueFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'total_tickets'          => 'integer',
            'tickets_resolus'        => 'integer',
            'tickets_en_cours'       => 'integer',
            'temps_moyen_resolution' => 'float',
            'periode_debut'          => 'date',
            'periode_fin'            => 'date',
        ];
    }

    
    // ─── Relations ───────────────────────────────────────────────

    public function generePar()
    {
        return $this->belongsTo(User::class, 'genere_par_id');
    }

    public function filtreClient()
    {
        return $this->belongsTo(Client::class, 'filtre_client_id');
    }

    public function filtreCategorie()
    {
        return $this->belongsTo(Categorie::class, 'filtre_categorie_id');
    }

    // ─── Helpers : calcul à la volée depuis les tickets ──────────

    public static function calculer(
        string $genereParId,
        ?\DateTime $periodeDebut = null,
        ?\DateTime $periodeFin = null,
        ?string $clientId = null,
        ?string $categorieId = null
    ): self {
        $query = Ticket::query();

        if ($periodeDebut) $query->where('created_at', '>=', $periodeDebut);
        if ($periodeFin)   $query->where('created_at', '<=', $periodeFin);
        if ($clientId)     $query->where('client_id', $clientId);
        if ($categorieId)  $query->where('categorie_id', $categorieId);

        $total   = (clone $query)->count();
        $resolus = (clone $query)->where('statut', 'resolu')->count();
        $enCours = (clone $query)->whereIn('statut', ['nouveau', 'en_cours', 'en_attente'])->count();

        $tempsMoyen = (clone $query)
            ->whereNotNull('date_resolution')
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (date_resolution - created_at)) / 3600) as moyenne')
            ->value('moyenne');

        return self::create([
            'genere_par_id'          => $genereParId,
            'total_tickets'          => $total,
            'tickets_resolus'        => $resolus,
            'tickets_en_cours'       => $enCours,
            'temps_moyen_resolution' => $tempsMoyen,
            'periode_debut'          => $periodeDebut,
            'periode_fin'            => $periodeFin,
            'filtre_client_id'       => $clientId,
            'filtre_categorie_id'    => $categorieId,
        ]);
    }
}
