<?php

namespace Database\Seeders;

use App\Models\Commentaire;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CommentaireSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tickets = Ticket::all();
        $users = User::all();

        if ($tickets->isEmpty() || $users->isEmpty()) {
            return;
        }

        foreach ($tickets as $ticket) {
            $count = fake()->numberBetween(1, 4);

            for ($i = 0; $i < $count; $i++) {
                Commentaire::factory()->create([
                    'ticket_id' => $ticket->id,
                    'auteur_id' => $users->random()->id,
                ]);
            }
        }
    }
}
