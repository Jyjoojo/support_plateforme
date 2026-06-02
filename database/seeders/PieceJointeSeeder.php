<?php

namespace Database\Seeders;

use App\Models\PieceJointe;
use App\Models\Ticket;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PieceJointeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tickets = Ticket::all();

        if ($tickets->isEmpty()) {
            return;
        }

        foreach ($tickets->random(min(25, $tickets->count())) as $ticket) {
            PieceJointe::factory()->count(fake()->numberBetween(1, 3))->create([
                'ticket_id' => $ticket->id,
            ]);
        }
    }
}
