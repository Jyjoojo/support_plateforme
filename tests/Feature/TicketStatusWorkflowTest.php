<?php

namespace Tests\Feature;

use App\Models\Assignation;
use App\Models\Client;
use App\Models\Technicien;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TicketStatusWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_technicien_ne_peut_modifier_que_la_priorite_via_patch(): void
    {
        [$technicien, $ticket] = $this->ticketAssigne('en_cours');
        Sanctum::actingAs($technicien->user);

        $this->patchJson("/api/tickets/{$ticket->id}", [
            'statut' => 'resolu',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('statut');

        $this->patchJson("/api/tickets/{$ticket->id}", [
            'priorite' => 'urgente',
        ])->assertOk();

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'statut' => 'en_cours',
            'priorite' => 'urgente',
        ]);
    }

    public function test_le_technicien_met_en_attente_puis_reprend_un_ticket(): void
    {
        [$technicien, $ticket] = $this->ticketAssigne('en_cours');
        Sanctum::actingAs($technicien->user);

        $this->postJson("/api/tickets/{$ticket->id}/mettre-en-attente")
            ->assertOk()
            ->assertJsonPath('ticket.statut', 'en_attente');

        $this->postJson("/api/tickets/{$ticket->id}/reprendre")
            ->assertOk()
            ->assertJsonPath('ticket.statut', 'en_cours');
    }

    public function test_un_ticket_ne_peut_etre_ferme_qu_apres_resolution(): void
    {
        $admin = User::factory()->administrateur()->create();
        [, $ticket] = $this->ticketAssigne('en_cours');
        Sanctum::actingAs($admin);

        $this->postJson("/api/tickets/{$ticket->id}/fermer")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('statut');

        $ticket->update(['statut' => 'resolu']);

        $this->postJson("/api/tickets/{$ticket->id}/fermer")
            ->assertOk()
            ->assertJsonPath('ticket.statut', 'ferme');
    }

    public function test_un_commentaire_solution_ne_resout_pas_un_ticket_nouveau(): void
    {
        [$technicien, $ticket] = $this->ticketAssigne('nouveau');
        Sanctum::actingAs($technicien->user);

        $this->postJson("/api/tickets/{$ticket->id}/commentaires", [
            'contenu' => 'Solution proposée.',
            'est_solution' => true,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('est_solution');

        $this->assertDatabaseMissing('commentaires', [
            'ticket_id' => $ticket->id,
            'contenu' => 'Solution proposée.',
        ]);
    }

    public function test_une_solution_met_le_ticket_en_attente_puis_le_client_confirme(): void
    {
        $client = Client::factory()->create();
        [$technicien, $ticket] = $this->ticketAssigne('en_cours', $client->id);
        Sanctum::actingAs($technicien->user);

        $response = $this->postJson("/api/tickets/{$ticket->id}/commentaires", [
            'contenu' => 'Redémarrez le service puis vérifiez son état.',
            'est_solution' => true,
        ])->assertCreated();

        $commentaireId = $response->json('commentaire.id');
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'statut' => 'en_attente', 'date_resolution' => null]);

        Sanctum::actingAs($client->user);
        $this->postJson("/api/tickets/{$ticket->id}/confirmer-resolution")
            ->assertOk()
            ->assertJsonPath('ticket.statut', 'resolu');

        $this->assertDatabaseHas('commentaires', ['id' => $commentaireId, 'solution_validee_par_id' => $client->user_id]);
        $this->assertDatabaseHas('article_bases', [
            'ticket_id' => $ticket->id,
            'commentaire_solution_id' => $commentaireId,
            'publie' => false,
        ]);
    }

    public function test_le_client_refuse_la_solution_avec_un_motif(): void
    {
        $client = Client::factory()->create();
        [$technicien, $ticket] = $this->ticketAssigne('en_cours', $client->id);
        Sanctum::actingAs($technicien->user);
        $this->postJson("/api/tickets/{$ticket->id}/commentaires", [
            'contenu' => 'Une solution à tester.',
            'est_solution' => true,
        ])->assertCreated();

        Sanctum::actingAs($client->user);
        $this->postJson("/api/tickets/{$ticket->id}/refuser-solution", [
            'motif' => 'Le problème est toujours présent après le test.',
        ])->assertOk()->assertJsonPath('ticket.statut', 'en_cours');

        $this->assertDatabaseMissing('article_bases', ['ticket_id' => $ticket->id]);
        $this->assertDatabaseHas('commentaires', [
            'ticket_id' => $ticket->id,
            'contenu' => 'Le problème est toujours présent après le test.',
            'est_solution' => false,
        ]);
    }

    public function test_un_client_ne_peut_pas_proposer_une_solution(): void
    {
        $client = Client::factory()->create();
        $ticket = Ticket::factory()->create(['client_id' => $client->id, 'statut' => 'en_cours']);
        Sanctum::actingAs($client->user);

        $this->postJson("/api/tickets/{$ticket->id}/commentaires", [
            'contenu' => 'Je marque moi-même cette réponse.',
            'est_solution' => true,
        ])->assertForbidden();
    }

    public function test_un_admin_ne_peut_plus_contourner_le_cycle_par_patch(): void
    {
        $admin = User::factory()->administrateur()->create();
        $ticket = Ticket::factory()->create(['statut' => 'en_cours']);
        Sanctum::actingAs($admin);

        $this->patchJson("/api/tickets/{$ticket->id}", ['statut' => 'resolu'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('statut');
    }

    private function ticketAssigne(string $statut, ?string $clientId = null): array
    {
        $technicien = Technicien::factory()->create();
        $ticket = Ticket::factory()->create(['statut' => $statut, ...($clientId ? ['client_id' => $clientId] : [])]);

        Assignation::factory()->create([
            'ticket_id' => $ticket->id,
            'technicien_id' => $technicien->id,
            'date_assignation' => now(),
        ]);

        return [$technicien, $ticket];
    }
}
