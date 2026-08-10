<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Technicien;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_technicien_peut_lister_les_clients_actifs(): void
    {
        Sanctum::actingAs(User::factory()->technicien()->create());

        $client = Client::factory()->create([
            'user_id' => User::factory()->client()->create([
                'nom' => 'Dupont',
                'prenom' => 'Alice',
                'email' => 'alice.dupont@example.test',
                'actif' => true,
            ]),
            'entreprise' => 'Exemple SARL',
        ]);

        Client::factory()->create([
            'user_id' => User::factory()->client()->create(['actif' => false]),
        ]);

        $this->getJson('/api/clients')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $client->id)
            ->assertJsonPath('data.0.nom_complet', 'Alice Dupont')
            ->assertJsonPath('data.0.entreprise', 'Exemple SARL')
            ->assertJsonPath('data.0.email', 'alice.dupont@example.test');
    }

    public function test_la_liste_des_clients_peut_etre_recherchee(): void
    {
        Sanctum::actingAs(User::factory()->technicien()->create());

        $clientTrouve = Client::factory()->create([
            'user_id' => User::factory()->client()->create([
                'nom' => 'Martin',
                'prenom' => 'Lina',
                'actif' => true,
            ]),
            'entreprise' => 'Soleil Conseil',
        ]);
        Client::factory()->create([
            'user_id' => User::factory()->client()->create(['actif' => true]),
            'entreprise' => 'Autre entreprise',
        ]);

        $this->getJson('/api/clients?search=Soleil')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $clientTrouve->id);
    }

    public function test_un_administrateur_peut_lister_les_clients(): void
    {
        Sanctum::actingAs(User::factory()->administrateur()->create());
        Client::factory()->create([
            'user_id' => User::factory()->client()->create(['actif' => true]),
        ]);

        $this->getJson('/api/clients')->assertOk();
    }

    public function test_un_technicien_peut_creer_un_ticket_pour_un_client_de_la_liste(): void
    {
        $technicien = Technicien::factory()->create();
        $client = Client::factory()->create([
            'user_id' => User::factory()->client()->create(['actif' => true]),
        ]);
        Sanctum::actingAs($technicien->user);

        $this->postJson('/api/tickets', [
            'titre' => 'Incident déclaré par téléphone',
            'description' => 'Le client ne peut plus accéder à son espace personnel.',
            'client_id' => $client->id,
        ])
            ->assertCreated()
            ->assertJsonPath('ticket.client.id', $client->id);

        $this->assertDatabaseHas('tickets', [
            'client_id' => $client->id,
            'createur_id' => $technicien->id,
            'source_creation' => 'technicien',
        ]);
    }

    public function test_un_client_ne_peut_pas_lister_les_autres_clients(): void
    {
        Sanctum::actingAs(User::factory()->client()->create());

        $this->getJson('/api/clients')->assertForbidden();
    }

    public function test_la_liste_des_clients_exige_une_authentification(): void
    {
        $this->getJson('/api/clients')->assertUnauthorized();
    }
}
