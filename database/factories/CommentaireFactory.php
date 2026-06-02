<?php

namespace Database\Factories;

use App\Models\Commentaire;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Commentaire>
 */
class CommentaireFactory extends Factory
{
    protected $model = Commentaire::class;

    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'auteur_id' => User::factory(),
            'contenu' => fake()->paragraphs(fake()->numberBetween(1, 3), true),
            'est_solution' => false,
        ];
    }
}
