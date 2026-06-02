<?php

namespace Database\Factories;

use App\Models\Categorie;
use App\Models\Client;
use App\Models\Statistique;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Statistique>
 */
class StatistiqueFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Statistique::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $total = fake()->numberBetween(0, 200);
        $resolved = fake()->numberBetween(0, $total);
        $enCours = fake()->numberBetween(0, $total - $resolved);

        return [
            'genere_par_id' => User::factory()->administrateur(),
            'total_tickets' => $total,
            'tickets_resolus' => $resolved,
            'tickets_en_cours' => $enCours,
            'temps_moyen_resolution' => fake()->optional(0.8)->randomFloat(2, 0.5, 72),
            'periode_debut' => fake()->dateTimeBetween('-90 days', '-30 days'),
            'periode_fin' => fake()->dateTimeBetween('-29 days', 'now'),
            'filtre_client_id' => fake()->boolean(70) ? Client::factory() : null,
            'filtre_categorie_id' => fake()->boolean(70) ? Categorie::factory() : null,
        ];
    }
}
