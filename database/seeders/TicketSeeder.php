<?php

namespace Database\Seeders;

use App\Models\Categorie;
use App\Models\Client;
use App\Models\Ticket;
use App\Models\Technicien;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TicketSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $clients = Client::all();
        $categories = Categorie::all();
        $techniciens = Technicien::all();
        $administrateurs = User::where('role', 'administrateur')->get();

        if ($clients->isEmpty() || $categories->isEmpty() || $administrateurs->isEmpty()) {
            return;
        }

        for ($i = 0; $i < 30; $i++) {
            $client = $clients->random();
            $categorie = $categories->random();
            $type = fake()->randomElement(['client', 'technicien', 'administrateur']);

            if ($type === 'technicien' && $techniciens->isEmpty()) {
                $type = 'administrateur';
            }

            $createurType = match ($type) {
                'technicien' => Technicien::class,
                'client' => Client::class,
                default => User::class,
            };

            $createurId = match ($type) {
                'technicien' => $techniciens->random()->id,
                'client' => $client->id,
                default => $administrateurs->random()->id,
            };

            $statut = fake()->randomElement(Ticket::STATUTS);
            $dateResolution = in_array($statut, ['resolu', 'ferme'])
                ? fake()->dateTimeBetween('-25 days', 'now')
                : null;

            Ticket::factory()->create([
                'client_id' => $client->id,
                'categorie_id' => $categorie->id,
                'createur_type' => $createurType,
                'createur_id' => $createurId,
                'statut' => $statut,
                'source_creation' => fake()->randomElement(Ticket::SOURCES),
                'date_resolution' => $dateResolution,
            ]);
        }
    }
}
