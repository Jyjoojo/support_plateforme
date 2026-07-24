<?php

namespace Tests\Feature;

use App\Models\Assignation;
use App\Models\Technicien;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TechnicienAssignationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_technicien_peut_voir_et_auto_assigner_un_ticket_libre(): void
    {
        $technicien = Technicien::factory()->create();
        $ticket = Ticket::factory()->create(['statut' => 'nouveau']);

        Sanctum::actingAs($technicien->user);

        $this->getJson('/api/tickets/non-assignes')
            ->assertOk()
            ->assertJsonFragment(['id' => $ticket->id]);

        $this->getJson("/api/tickets/{$ticket->id}")->assertOk();

        $this->postJson("/api/tickets/{$ticket->id}/auto-assigner")
            ->assertCreated()
            ->assertJsonPath('assignation.methode', 'auto_assignation');

        $this->assertDatabaseHas('assignations', [
            'ticket_id' => $ticket->id,
            'technicien_id' => $technicien->id,
        ]);
        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'statut' => 'en_cours',
        ]);
    }

    public function test_un_technicien_ne_peut_pas_auto_assigner_un_ticket_deja_assigne(): void
    {
        $technicien = Technicien::factory()->create();
        $ticket = Ticket::factory()->create(['statut' => 'nouveau']);
        Assignation::factory()->create(['ticket_id' => $ticket->id]);

        Sanctum::actingAs($technicien->user);

        $this->postJson("/api/tickets/{$ticket->id}/auto-assigner")
            ->assertConflict()
            ->assertJsonPath('message', 'Ce ticket est déjà assigné.');
    }

    public function test_seul_le_technicien_actif_peut_transferer_un_ticket_ouvert(): void
    {
        $technicienActif = Technicien::factory()->create();
        $autreTechnicien = Technicien::factory()->create();
        $destinataire = Technicien::factory()->create();
        $ticket = Ticket::factory()->create(['statut' => 'en_cours']);

        Assignation::factory()->create([
            'ticket_id' => $ticket->id,
            'technicien_id' => $technicienActif->id,
            'date_assignation' => now(),
        ]);

        Sanctum::actingAs($autreTechnicien->user);
        $this->postJson("/api/tickets/{$ticket->id}/assignation", [
            'technicien_id' => $destinataire->id,
        ])->assertForbidden();

        Sanctum::actingAs($technicienActif->user);
        $this->postJson("/api/tickets/{$ticket->id}/assignation", [
            'technicien_id' => $destinataire->id,
            'motif' => 'Transfert vers un spécialiste.',
        ])->assertCreated();

        $this->assertDatabaseHas('assignations', [
            'ticket_id' => $ticket->id,
            'technicien_id' => $destinataire->id,
            'motif' => 'Transfert vers un spécialiste.',
        ]);
    }

    public function test_un_technicien_ne_peut_pas_transferer_un_ticket_ferme(): void
    {
        $technicien = Technicien::factory()->create();
        $destinataire = Technicien::factory()->create();
        $ticket = Ticket::factory()->create(['statut' => 'ferme']);

        Assignation::factory()->create([
            'ticket_id' => $ticket->id,
            'technicien_id' => $technicien->id,
            'date_assignation' => now(),
        ]);

        Sanctum::actingAs($technicien->user);

        $this->postJson("/api/tickets/{$ticket->id}/assignation", [
            'technicien_id' => $destinataire->id,
        ])->assertUnprocessable();
    }
}
