<?php

namespace Tests\Feature;

use App\Models\ArticleBase;
use App\Models\Client;
use App\Models\Commentaire;
use App\Models\Technicien;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ArticleApiFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_client_voit_ses_resolutions_y_compris_les_brouillons(): void
    {
        $client = Client::factory()->create();
        $autreClient = Client::factory()->create();
        $brouillon = $this->creerResolutionPour($client, false);
        $articleAutreClient = $this->creerResolutionPour($autreClient, true);

        Sanctum::actingAs($client->user);

        $this->getJson('/api/client/resolutions')
            ->assertOk()
            ->assertJsonPath('data.0.id', $brouillon->id)
            ->assertJsonPath('data.0.ticket.id', $brouillon->ticket_id)
            ->assertJsonPath('data.0.ticket.reference', $brouillon->ticket->reference)
            ->assertJsonPath('data.0.ticket.titre', $brouillon->ticket->titre)
            ->assertJsonPath('data.0.ticket.statut', 'resolu')
            ->assertJsonPath('data.0.ticket.date_resolution', $brouillon->ticket->date_resolution->toDateTimeString())
            ->assertJsonPath('data.0.ticket.client_id', $client->id)
            ->assertJsonMissingPath('data.0.ticket.annee')
            ->assertJsonMissingPath('data.0.ticket.numero')
            ->assertJsonMissing(['id' => $articleAutreClient->id]);
    }

    public function test_la_route_des_resolutions_est_reservee_aux_clients(): void
    {
        Sanctum::actingAs(User::factory()->technicien()->create());

        $this->getJson('/api/client/resolutions')->assertForbidden();
    }

    public function test_un_client_choisit_la_taille_de_page_de_ses_resolutions(): void
    {
        $client = Client::factory()->create();

        foreach (range(1, 13) as $_) {
            $this->creerResolutionPour($client, false);
        }

        Sanctum::actingAs($client->user);

        $this->getJson('/api/client/resolutions?perPage=12')
            ->assertOk()
            ->assertJsonCount(12, 'data')
            ->assertJsonPath('per_page', 12)
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('last_page', 2)
            ->assertJsonPath('total', 13);
    }

    private function creerResolutionPour(Client $client, bool $publie): ArticleBase
    {
        $ticket = Ticket::factory()->create([
            'client_id' => $client->id,
            'statut' => 'resolu',
            'date_resolution' => now(),
        ]);
        $solution = Commentaire::factory()->create([
            'ticket_id' => $ticket->id,
            'est_solution' => true,
            'solution_validee_at' => now(),
            'solution_validee_par_id' => $client->user_id,
        ]);

        return ArticleBase::factory()->create([
            'ticket_id' => $ticket->id,
            'commentaire_solution_id' => $solution->id,
            'publie' => $publie,
        ]);
    }

    public function test_la_liste_publique_ne_retourne_que_les_articles_publies_non_archives(): void
    {
        $publie = ArticleBase::factory()->create(['publie' => true]);
        $brouillon = ArticleBase::factory()->create(['publie' => false]);
        $archive = ArticleBase::factory()->create(['publie' => true]);
        $archive->delete();

        $this->getJson('/api/articles')
            ->assertOk()
            ->assertJsonFragment(['id' => $publie->id])
            ->assertJsonMissing(['id' => $brouillon->id])
            ->assertJsonMissing(['id' => $archive->id]);
    }

    public function test_un_technicien_peut_filtrer_les_brouillons(): void
    {
        $technicien = Technicien::factory()->create();
        $publie = ArticleBase::factory()->create(['publie' => true]);
        $brouillon = ArticleBase::factory()->create(['publie' => false]);
        Sanctum::actingAs($technicien->user);

        $this->getJson('/api/articles?publie=0')
            ->assertOk()
            ->assertJsonFragment(['id' => $brouillon->id])
            ->assertJsonMissing(['id' => $publie->id]);
    }

    public function test_la_pagination_des_articles_applique_le_defaut_et_les_bornes(): void
    {
        ArticleBase::factory()->create(['publie' => true]);

        $this->getJson('/api/articles')
            ->assertOk()
            ->assertJsonPath('per_page', 6);

        $this->getJson('/api/articles?perPage=5')
            ->assertOk()
            ->assertJsonPath('per_page', 6);

        $this->getJson('/api/articles?perPage=150')
            ->assertOk()
            ->assertJsonPath('per_page', 100);

        $this->getJson('/api/articles?perPage=invalide')
            ->assertOk()
            ->assertJsonPath('per_page', 6);
    }

    public function test_le_filtre_with_archived_inclut_les_articles_archives_pour_un_admin(): void
    {
        $admin = User::factory()->administrateur()->create();
        $actif = ArticleBase::factory()->create();
        $archive = ArticleBase::factory()->create();
        $archive->delete();
        Sanctum::actingAs($admin);

        $this->getJson('/api/articles?with_archived=1')
            ->assertOk()
            ->assertJsonFragment(['id' => $actif->id])
            ->assertJsonFragment(['id' => $archive->id]);
    }

    public function test_le_filtre_only_archived_exclut_les_articles_actifs_pour_un_admin(): void
    {
        $admin = User::factory()->administrateur()->create();
        $actif = ArticleBase::factory()->create();
        $archive = ArticleBase::factory()->create();
        $archive->delete();
        Sanctum::actingAs($admin);

        $this->getJson('/api/articles?only_archived=1')
            ->assertOk()
            ->assertJsonFragment(['id' => $archive->id])
            ->assertJsonMissing(['id' => $actif->id]);
    }

    public function test_les_archives_sont_reservees_aux_administrateurs(): void
    {
        $technicien = Technicien::factory()->create();
        Sanctum::actingAs($technicien->user);

        $this->getJson('/api/articles?with_archived=1')->assertForbidden();
    }
}
