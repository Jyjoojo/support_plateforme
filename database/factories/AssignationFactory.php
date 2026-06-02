<?php

namespace Database\Factories;

use App\Models\Assignation;
use App\Models\Technicien;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assignation>
 */
class AssignationFactory extends Factory
{
    protected $model = Assignation::class;

    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'technicien_id' => Technicien::factory(),
            'assigne_par_id' => User::factory()->administrateur(),
            'methode' => fake()->randomElement(Assignation::METHODES),
            'motif' => fake()->sentence(),
            'date_assignation' => fake()->dateTimeBetween('-15 days', 'now'),
        ];
    }
}
