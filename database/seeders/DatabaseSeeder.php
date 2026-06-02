<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CategorieSeeder::class,
            AdministrateurSeeder::class,
            ClientSeeder::class,
            TechnicienSeeder::class,
            TicketSeeder::class,
            AssignationSeeder::class,
            CommentaireSeeder::class,
            PieceJointeSeeder::class,
            NotificationSeeder::class,
            ArticleBaseSeeder::class,
            StatistiqueSeeder::class,
        ]);
    }
}
