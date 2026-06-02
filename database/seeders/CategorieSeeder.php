<?php

namespace Database\Seeders;

use App\Models\Categorie;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorieSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['libelle' => 'Infrastructure', 'description' => 'Problèmes liés aux serveurs et aux réseaux.'],
            ['libelle' => 'Support technique', 'description' => 'Demandes d’assistance et dépannage logiciel.'],
            ['libelle' => 'Sécurité', 'description' => 'Incidents ou questions de sécurité.'],
            ['libelle' => 'Performance', 'description' => 'Améliorations et lenteurs système.'],
            ['libelle' => 'Configuration', 'description' => 'Paramétrage et réglages de la plateforme.'],
            ['libelle' => 'Facturation', 'description' => 'Questions liées aux factures et paiements.'],
        ];

        foreach ($categories as $categorie) {
            Categorie::factory()->create($categorie);
        }
    }
}
