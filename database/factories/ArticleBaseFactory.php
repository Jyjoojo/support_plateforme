<?php

namespace Database\Factories;

use App\Models\ArticleBase;
use App\Models\Categorie;
use App\Models\Technicien;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArticleBase>
 */
class ArticleBaseFactory extends Factory
{
    protected $model = ArticleBase::class;

    public function definition(): array
    {
        return [
            'technicien_id' => Technicien::factory(),
            'categorie_id' => Categorie::factory(),
            'titre' => fake()->sentence(6),
            'contenu' => fake()->paragraphs(fake()->numberBetween(3, 7), true),
            'mots_cles' => implode(', ', fake()->words(fake()->numberBetween(3, 6))),
            'vues' => fake()->numberBetween(0, 5000),
            'publie' => fake()->boolean(70),
        ];
    }
}
