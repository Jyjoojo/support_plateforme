<?php

namespace Database\Seeders;

use App\Models\Categorie;
use App\Models\Client;
use App\Models\Statistique;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StatistiqueSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::where('role', 'administrateur')->inRandomOrder()->first();

        if (! $admin) {
            return;
        }

        $totalTickets = Ticket::count();
        $resolus = Ticket::where('statut', 'resolu')->count();
        $enCours = Ticket::whereIn('statut', ['nouveau', 'en_cours', 'en_attente'])->count();
        $resolvedTickets = Ticket::whereNotNull('date_resolution')->get();
        $tempsMoyen = $resolvedTickets->isNotEmpty()
            ? $resolvedTickets->average(fn ($ticket) => $ticket->date_resolution->floatDiffInHours($ticket->created_at))
            : null;

        Statistique::factory()->create([
            'genere_par_id' => $admin->id,
            'total_tickets' => $totalTickets,
            'tickets_resolus' => $resolus,
            'tickets_en_cours' => $enCours,
            'temps_moyen_resolution' => $tempsMoyen,
            'periode_debut' => now()->subDays(30)->toDateString(),
            'periode_fin' => now()->toDateString(),
            'filtre_client_id' => Client::inRandomOrder()->first()?->id,
            'filtre_categorie_id' => Categorie::inRandomOrder()->first()?->id,
        ]);

        Statistique::factory()->count(3)->create([
            'genere_par_id' => $admin->id,
        ]);
    }
}
