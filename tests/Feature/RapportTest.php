<?php

namespace Tests\Feature;

use App\Models\ArticleBase;
use App\Models\Assignation;
use App\Models\Categorie;
use App\Models\Client;
use App\Models\Technicien;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RapportTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_administrateur_peut_consulter_les_rapports_separes(): void
    {
        Sanctum::actingAs(User::factory()->administrateur()->create());

        $categorie = Categorie::factory()->create(['libelle' => 'Réseau']);
        $client = Client::factory()->create();

        Ticket::factory()->create([
            'categorie_id' => $categorie->id,
            'client_id' => $client->id,
            'statut' => 'nouveau',
            'created_at' => now()->subDays(8),
        ]);
        Ticket::factory()->create([
            'categorie_id' => $categorie->id,
            'client_id' => $client->id,
            'statut' => 'resolu',
        ]);

        ArticleBase::factory()->create([
            'categorie_id' => $categorie->id,
            'publie' => true,
            'vues' => 42,
        ]);
        ArticleBase::factory()->create([
            'categorie_id' => $categorie->id,
            'publie' => false,
        ]);
        $archive = ArticleBase::factory()->create([
            'categorie_id' => $categorie->id,
            'publie' => true,
        ]);
        $archive->archiver();

        $this->getJson('/api/rapports/tickets?jours_retard=7&limite=5')
            ->assertOk()
            ->assertJsonPath('par_statut.nouveau', 1)
            ->assertJsonPath('par_statut.resolu', 1)
            ->assertJsonPath('par_categorie.0.total', 2)
            ->assertJsonPath('en_retard.total', 1);

        $this->getJson('/api/rapports/base-de-connaissances?limite=5')
            ->assertOk()
            ->assertJsonPath('par_etat.publies', 1)
            ->assertJsonPath('par_etat.brouillons', 1)
            ->assertJsonPath('par_etat.archives', 1)
            ->assertJsonPath('par_categorie.0.total', 2);

        $this->getJson('/api/rapports/clients?limite=5')
            ->assertOk()
            ->assertJsonPath('plus_de_tickets_ouverts.0.tickets_ouverts', 1)
            ->assertJsonStructure([
                'plus_de_tickets_ouverts',
                'nouveaux_comptes' => ['total', 'elements'],
            ]);

        $this->assertSoftDeleted('article_bases', ['id' => $archive->id]);
    }

    public function test_les_rapports_sont_reserves_aux_administrateurs(): void
    {
        Sanctum::actingAs(User::factory()->technicien()->create());

        $this->getJson('/api/rapports/tickets')->assertForbidden();
        $this->getJson('/api/rapports/base-de-connaissances')->assertForbidden();
        $this->getJson('/api/rapports/clients')->assertForbidden();
    }

    public function test_les_filtres_des_rapports_sont_valides(): void
    {
        Sanctum::actingAs(User::factory()->administrateur()->create());

        $this->getJson('/api/rapports/tickets?jours_retard=0&limite=101')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['jours_retard', 'limite']);

        $this->getJson('/api/rapports/clients?limite=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['limite']);
    }

    public function test_les_rapports_de_categorie_deux_exposent_evolution_resolution_et_charge(): void
    {
        Sanctum::actingAs(User::factory()->administrateur()->create());

        $categorie = Categorie::factory()->create();
        $technicien = Technicien::factory()->create();
        $ticket = Ticket::factory()->create([
            'categorie_id' => $categorie->id,
            'statut' => 'resolu',
            'created_at' => now()->subDays(2),
            'date_resolution' => now()->subDay(),
        ]);
        Assignation::factory()->create([
            'ticket_id' => $ticket->id,
            'technicien_id' => $technicien->id,
            'date_assignation' => now()->subDays(2),
        ]);

        $query = http_build_query([
            'periode_debut' => now()->subDays(5)->toDateString(),
            'periode_fin' => now()->toDateString(),
            'categorie_id' => $categorie->id,
            'technicien_id' => $technicien->id,
            'granularite' => 'jour',
        ]);

        $this->getJson("/api/rapports/tickets?{$query}")
            ->assertOk()
            ->assertJsonPath('temps_resolution.global', 24)
            ->assertJsonPath('temps_resolution.par_categorie.0.tickets_resolus', 1)
            ->assertJsonFragment(['crees' => 1])
            ->assertJsonFragment(['resolus' => 1]);

        $this->getJson("/api/rapports/techniciens?{$query}")
            ->assertOk()
            ->assertJsonPath('charge.0.technicien_id', $technicien->id)
            ->assertJsonPath('charge.0.tickets_assignes', 1)
            ->assertJsonPath('charge.0.tickets_resolus', 1)
            ->assertJsonPath('charge.0.taux_resolution', 100);
    }
}
