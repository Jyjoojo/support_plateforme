<?php

namespace Tests\Feature;

use App\Models\Assignation;
use App\Models\Technicien;
use App\Models\Ticket;
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
        [$technicien, $ticket] = $this->ticketAssigne('en_cours');
        Sanctum::actingAs($technicien->user);

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

    private function ticketAssigne(string $statut): array
    {
        $technicien = Technicien::factory()->create();
        $ticket = Ticket::factory()->create(['statut' => $statut]);

        Assignation::factory()->create([
            'ticket_id' => $ticket->id,
            'technicien_id' => $technicien->id,
            'date_assignation' => now(),
        ]);

        return [$technicien, $ticket];
    }
}
