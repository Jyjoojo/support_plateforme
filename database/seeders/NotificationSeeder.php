<?php

namespace Database\Seeders;

use App\Models\Notification;
use App\Models\Ticket;
use App\Models\User;
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

        for ($i = 0; $i < 40; $i++) {
            Notification::factory()->create([
                'user_id' => $users->random()->id,
                'ticket_id' => $tickets->isNotEmpty() && fake()->boolean(80)
                    ? $tickets->random()->id
                    : null,
            ]);
        }
    }
}
