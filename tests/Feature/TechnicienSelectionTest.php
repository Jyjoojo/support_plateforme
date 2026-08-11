<?php

namespace Tests\Feature;

use App\Models\Technicien;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TechnicienSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_technicien_peut_rechercher_les_autres_techniciens_actifs(): void
    {
        $technicienConnecte = Technicien::factory()->create([
            'user_id' => User::factory()->technicien()->create(['actif' => true]),
        ]);
        $destinataire = Technicien::factory()->create([
            'user_id' => User::factory()->technicien()->create([
                'nom' => 'Martin',
                'prenom' => 'Lina',
                'email' => 'lina.martin@example.test',
                'actif' => true,
            ]),
            'specialite' => 'Réseau',
            'tickets_en_cours' => 2,
        ]);
        Technicien::factory()->create([
            'user_id' => User::factory()->technicien()->create(['actif' => false]),
            'specialite' => 'Réseau',
        ]);
        Sanctum::actingAs($technicienConnecte->user);

        $this->getJson('/api/techniciens?search=Réseau')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $destinataire->id)
            ->assertJsonPath('data.0.nom_complet', 'Lina Martin')
            ->assertJsonPath('data.0.email', 'lina.martin@example.test')
            ->assertJsonPath('data.0.specialite', 'Réseau')
            ->assertJsonPath('data.0.tickets_en_cours', 2);
    }

    public function test_un_client_ne_peut_pas_lister_les_techniciens(): void
    {
        Sanctum::actingAs(User::factory()->client()->create());

        $this->getJson('/api/techniciens')->assertForbidden();
    }

    public function test_la_liste_des_techniciens_exige_une_authentification(): void
    {
        $this->getJson('/api/techniciens')->assertUnauthorized();
    }
}
