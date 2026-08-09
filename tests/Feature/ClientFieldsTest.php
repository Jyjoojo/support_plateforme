<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_administrateur_peut_creer_un_client_avec_son_adresse_et_son_pays(): void
    {
        Sanctum::actingAs(User::factory()->administrateur()->create());

        $this->postJson('/api/users', [
            'nom' => 'Dupont',
            'prenom' => 'Alice',
            'email' => 'alice.dupont@example.test',
            'password' => 'mot-de-passe',
            'role' => 'client',
            'entreprise' => 'Exemple SARL',
            'secteur' => 'Services',
            'adresse' => '12 rue des Lilas, 75001 Paris',
            'pays' => 'France',
        ])
            ->assertCreated()
            ->assertJsonPath('user.client.adresse', '12 rue des Lilas, 75001 Paris')
            ->assertJsonPath('user.client.pays', 'France');

        $this->assertDatabaseHas('clients', [
            'adresse' => '12 rue des Lilas, 75001 Paris',
            'pays' => 'France',
        ]);
    }

    public function test_un_administrateur_peut_modifier_adresse_et_pays_du_client(): void
    {
        Sanctum::actingAs(User::factory()->administrateur()->create());
        $client = Client::factory()->create();

        $this->patchJson("/api/users/{$client->user_id}", [
            'adresse' => '8 avenue Centrale, Montréal',
            'pays' => 'Canada',
        ])
            ->assertOk()
            ->assertJsonPath('user.client.adresse', '8 avenue Centrale, Montréal')
            ->assertJsonPath('user.client.pays', 'Canada');

        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'adresse' => '8 avenue Centrale, Montréal',
            'pays' => 'Canada',
        ]);
    }
}
