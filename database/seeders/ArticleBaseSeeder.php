<?php

namespace Database\Seeders;

use App\Models\ArticleBase;
use App\Models\Categorie;
use App\Models\Technicien;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ArticleBaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $techniciens = Technicien::all();
        $categories = Categorie::all();

        if ($techniciens->isEmpty() || $categories->isEmpty()) {
            return;
        }

        foreach ($techniciens as $technicien) {
            ArticleBase::factory()->count(3)->create([
                'technicien_id' => $technicien->id,
                'categorie_id' => $categories->random()->id,
            ]);
        }
    }
}
