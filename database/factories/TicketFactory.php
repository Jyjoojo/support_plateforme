<?php

namespace Database\Factories;

use App\Models\Categorie;
use App\Models\Client;
use App\Models\Ticket;
use App\Models\Technicien;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        $statut = fake()->randomElement(Ticket::STATUTS);
        $dateResolution = in_array($statut, ['resolu', 'ferme'])
            ? fake()->dateTimeBetween('-30 days', 'now')
            : null;

        $createurModele = fake()->randomElement(['client', 'technicien', 'administrateur']);
        return [
            'titre' => fake()->sentence(6),
            'description' => fake()->paragraphs(fake()->numberBetween(2, 5), true),
            'statut' => $statut,
            'priorite' => fake()->randomElement(Ticket::PRIORITES),
            'source_creation' => fake()->randomElement(Ticket::SOURCES),
            'createur_type' => match ($createurModele) {
                'technicien' => Technicien::class,
                'client' => Client::class,
                default => User::class,
            },
            'createur_id' => match ($createurModele) {
                'technicien' => Technicien::factory(),
                'client' => Client::factory(),
                default => User::factory()->administrateur(),
            },
            'client_id' => Client::factory(),
            'categorie_id' => Categorie::factory(),
            'date_resolution' => $dateResolution,
        ];
    }
}
