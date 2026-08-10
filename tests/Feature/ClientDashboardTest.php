<?php

namespace Tests\Feature;

use App\Models\ArticleBase;
use App\Models\Categorie;
use App\Models\Client;
use App\Models\Commentaire;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_dashboard_retourne_les_compteurs_et_les_quatre_tickets_recents_du_client(): void
    {
        $client = Client::factory()->create();
        $autreClient = Client::factory()->create();
        $categorie = Categorie::factory()->create(['libelle' => 'Réseau']);

        $plusAncien = $this->creerTicket($client, $categorie, 'nouveau', now()->subDays(5));
        $this->creerTicket($client, $categorie, 'en_cours', now()->subDays(4));
        $solutionEnAttente = $this->creerTicket($client, $categorie, 'en_attente', now()->subDays(3));
        $this->creerSolutionEnAttente($solutionEnAttente);
        $this->creerTicket($client, $categorie, 'resolu', now()->subDays(2));
        $plusRecent = $this->creerTicket($client, $categorie, 'ferme', now()->subDay());

        $ticketAutreClient = $this->creerTicket($autreClient, $categorie, 'en_attente', now());
        $this->creerSolutionEnAttente($ticketAutreClient);

        $articleLePlusVu = ArticleBase::factory()->create([
            'categorie_id' => $categorie->id,
            'publie' => true,
            'titre' => 'Article populaire',
            'vues' => 100,
        ]);
        ArticleBase::factory()->create(['publie' => true, 'vues' => 75]);
        ArticleBase::factory()->create(['publie' => true, 'vues' => 50]);
        $quatriemeArticle = ArticleBase::factory()->create(['publie' => true, 'vues' => 25]);
        $brouillonTresVu = ArticleBase::factory()->create(['publie' => false, 'vues' => 500]);

        Sanctum::actingAs($client->user);

        $this->getJson('/api/client/dashboard')
            ->assertOk()
            ->assertJsonPath('tickets_actifs', 3)
            ->assertJsonPath('solutions_en_attente', 1)
            ->assertJsonCount(4, 'tickets_recents')
            ->assertJsonPath('tickets_recents.0.id', $plusRecent->id)
            ->assertJsonPath('tickets_recents.0.reference', $plusRecent->reference)
            ->assertJsonPath('tickets_recents.0.categorie.libelle', 'Réseau')
            ->assertJsonPath('tickets_recents.2.id', $solutionEnAttente->id)
            ->assertJsonMissingPath('tickets_recents.0.actions')
            ->assertJsonMissing(['id' => $plusAncien->id])
            ->assertJsonMissing(['id' => $ticketAutreClient->id])
            ->assertJsonCount(3, 'articles_plus_vus')
            ->assertJsonPath('articles_plus_vus.0.id', $articleLePlusVu->id)
            ->assertJsonPath('articles_plus_vus.0.titre', 'Article populaire')
            ->assertJsonPath('articles_plus_vus.0.categorie.libelle', 'Réseau')
            ->assertJsonPath('articles_plus_vus.0.vues', 100)
            ->assertJsonMissing(['id' => $quatriemeArticle->id])
            ->assertJsonMissing(['id' => $brouillonTresVu->id]);
    }

    public function test_le_dashboard_client_est_interdit_aux_autres_roles(): void
    {
        Sanctum::actingAs(User::factory()->technicien()->create());

        $this->getJson('/api/client/dashboard')->assertForbidden();
    }

    private function creerTicket(Client $client, Categorie $categorie, string $statut, \DateTimeInterface $date): Ticket
    {
        return Ticket::factory()->create([
            'client_id' => $client->id,
            'categorie_id' => $categorie->id,
            'statut' => $statut,
            'created_at' => $date,
            'updated_at' => $date,
        ]);
    }

    private function creerSolutionEnAttente(Ticket $ticket): void
    {
        Commentaire::factory()->create([
            'ticket_id' => $ticket->id,
            'est_solution' => true,
            'solution_validee_at' => null,
            'solution_rejetee_at' => null,
        ]);
    }
}
