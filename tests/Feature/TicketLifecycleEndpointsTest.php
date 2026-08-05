<?php

namespace Tests\Feature;

use App\Models\Assignation;
use App\Models\Technicien;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TicketLifecycleEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_ticket_assigne_peut_etre_mis_en_attente_puis_repris(): void
    {
        [$technicien, $ticket] = $this->ticketAssigne('en_cours');
        Sanctum::actingAs($technicien->user);

        $this->postJson("/api/tickets/{$ticket->id}/mettre-en-attente")
            ->assertOk()
            ->assertJsonPath('ticket.statut', 'en_attente');

        $this->postJson("/api/tickets/{$ticket->id}/reprendre")
            ->assertOk()
            ->assertJsonPath('ticket.statut', 'en_cours');

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'statut' => 'en_cours',
        ]);
    }

    public function test_un_ticket_resolu_peut_etre_ferme_puis_reouvert(): void
    {
        $admin = User::factory()->administrateur()->create();
        [, $ticket] = $this->ticketAssigne('resolu');
        Sanctum::actingAs($admin);

        $this->postJson("/api/tickets/{$ticket->id}/fermer")
            ->assertOk()
            ->assertJsonPath('ticket.statut', 'ferme');

        $this->postJson("/api/tickets/{$ticket->id}/reouvrir")
            ->assertOk()
            ->assertJsonPath('ticket.statut', 'en_cours');

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'statut' => 'en_cours',
            'date_resolution' => null,
        ]);
    }

    public function test_une_transition_invalide_est_rejetee_sans_modifier_le_ticket(): void
    {
        [$technicien, $ticket] = $this->ticketAssigne('nouveau');
        Sanctum::actingAs($technicien->user);

        $this->postJson("/api/tickets/{$ticket->id}/fermer")
            ->assertForbidden();

        $this->postJson("/api/tickets/{$ticket->id}/reouvrir")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('statut');

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'statut' => 'nouveau',
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
