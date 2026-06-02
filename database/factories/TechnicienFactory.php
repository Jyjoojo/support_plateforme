<?php

namespace Database\Factories;

use App\Models\Technicien;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Technicien>
 */
class TechnicienFactory extends Factory
{
    protected $model = Technicien::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->technicien(),
            'specialite' => fake()->word(),
            'tickets_en_cours' => fake()->numberBetween(0, 5),
        ];
    }
}
