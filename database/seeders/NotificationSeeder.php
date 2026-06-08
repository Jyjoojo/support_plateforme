<?php

namespace Database\Seeders;

use App\Models\Ticket;
use App\Models\User;
use App\Notifications\SimpleNotification;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        $tickets = Ticket::all();

        if ($users->isEmpty()) {
            return;
        }

        $types = [
            'nouveau_ticket',
            'ticket_assigne',
            'nouveau_commentaire',
            'statut_change',
            'ticket_resolu',
            'rappel',
        ];

        for ($i = 0; $i < 40; $i++) {
            $user = $users->random();
            $ticketId = $tickets->isNotEmpty() && fake()->boolean(80)
                ? $tickets->random()->id
                : null;

            $user->notify(new SimpleNotification(
                fake()->sentence(),
                $types[array_rand($types)],
                $ticketId
            ));
        }
    }
}
