<?php

namespace Database\Factories;

use App\Models\Categorie;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Categorie>
 */
class CategorieFactory extends Factory
{
    protected $model = Categorie::class;

    public function definition(): array
    {
        return [
            'libelle' => fake()->unique()->word(),
            'description' => fake()->optional()->sentence(10),
        ];
    }
}
