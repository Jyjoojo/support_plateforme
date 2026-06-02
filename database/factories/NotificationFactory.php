<?php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'ticket_id' => fake()->boolean(80) ? Ticket::factory() : null,
            'message' => fake()->sentence(),
            'type' => fake()->randomElement(Notification::TYPES),
            'est_lue' => fake()->boolean(40),
            'date_envoi' => fake()->dateTimeBetween('-15 days', 'now'),
        ];
    }
}
