<?php

namespace Tests\Feature;

use App\Models\Assignation;
use App\Models\Client;
use App\Models\Technicien;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TicketPolicyFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_client_ne_peut_pas_modifier_le_ticket_d_un_autre_client(): void
    {
        $client = Client::factory()->create();
        $ticket = Ticket::factory()->create([
            'client_id' => Client::factory(),
            'statut' => 'nouveau',
        ]);

        Sanctum::actingAs($client->user);

        $this->patchJson("/api/tickets/{$ticket->id}", [
            'titre' => 'Titre modifie par erreur',
        ])->assertForbidden();

        $this->assertDatabaseMissing('tickets', [
            'id' => $ticket->id,
            'titre' => 'Titre modifie par erreur',
        ]);
    }

    public function test_un_client_peut_modifier_son_propre_ticket_nouveau(): void
    {
        $client = Client::factory()->create();
        $ticket = Ticket::factory()->create([
            'client_id' => $client->id,
            'statut' => 'nouveau',
        ]);

        Sanctum::actingAs($client->user);

        $this->patchJson("/api/tickets/{$ticket->id}", [
            'titre' => 'Titre correctement modifie',
        ])->assertOk();

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'titre' => 'Titre correctement modifie',
        ]);
    }

    public function test_un_technicien_non_assigne_ne_peut_pas_fermer_un_ticket(): void
    {
        $technicienAssigne = Technicien::factory()->create();
        $autreTechnicien = Technicien::factory()->create();
        $ticket = Ticket::factory()->create(['statut' => 'resolu']);

        Assignation::factory()->create([
            'ticket_id' => $ticket->id,
            'technicien_id' => $technicienAssigne->id,
            'date_assignation' => now(),
        ]);

        Sanctum::actingAs($autreTechnicien->user);

        $this->postJson("/api/tickets/{$ticket->id}/fermer")
            ->assertForbidden();

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'statut' => 'resolu',
        ]);
    }
}
