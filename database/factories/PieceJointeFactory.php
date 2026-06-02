<?php

namespace Database\Factories;

use App\Models\PieceJointe;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PieceJointe>
 */
class PieceJointeFactory extends Factory
{
    protected $model = PieceJointe::class;

    public function definition(): array
    {
        $filename = fake()->word() . '.' . fake()->fileExtension();

        return [
            'ticket_id' => Ticket::factory(),
            'nom_fichier' => $filename,
            'chemin_fichier' => '/storage/attachments/' . $filename,
            'type_mime' => fake()->mimeType(),
            'taille' => fake()->numberBetween(1024, 3_000_000),
            'date_upload' => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }
}
