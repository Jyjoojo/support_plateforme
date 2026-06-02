<?php

namespace Database\Seeders;

use App\Models\Assignation;
use App\Models\Ticket;
use App\Models\Technicien;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AssignationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tickets = Ticket::all();
        $techniciens = Technicien::all();
        $administrateurs = User::where('role', 'administrateur')->get();

        if ($tickets->isEmpty() || $techniciens->isEmpty() || $administrateurs->isEmpty()) {
            return;
        }

        foreach ($tickets as $ticket) {
            if (! fake()->boolean(75)) {
                continue;
            }

            Assignation::factory()->create([
                'ticket_id' => $ticket->id,
                'technicien_id' => $techniciens->random()->id,
                'assigne_par_id' => $administrateurs->random()->id,
            ]);
        }
    }
}
