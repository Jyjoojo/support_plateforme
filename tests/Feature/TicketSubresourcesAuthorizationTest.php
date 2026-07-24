<?php

namespace Tests\Feature;

use App\Models\Assignation;
use App\Models\Commentaire;
use App\Models\PieceJointe;
use App\Models\Technicien;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TicketSubresourcesAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_technicien_ne_peut_pas_lister_les_commentaires_d_un_ticket_non_assigne(): void
    {
        $technicien = Technicien::factory()->create();
        $ticket = Ticket::factory()->create();
        Commentaire::factory()->create(['ticket_id' => $ticket->id]);

        Sanctum::actingAs($technicien->user);

        $this->getJson("/api/tickets/{$ticket->id}/commentaires")
            ->assertForbidden();
    }

    public function test_un_technicien_ne_peut_pas_lister_les_pieces_jointes_d_un_ticket_non_assigne(): void
    {
        $technicien = Technicien::factory()->create();
        $ticket = Ticket::factory()->create();
        PieceJointe::factory()->create(['ticket_id' => $ticket->id]);

        Sanctum::actingAs($technicien->user);

        $this->getJson("/api/tickets/{$ticket->id}/pieces-jointes")
            ->assertForbidden();
    }

    public function test_un_technicien_peut_lister_les_commentaires_et_pieces_jointes_d_un_ticket_assigne(): void
    {
        $technicien = Technicien::factory()->create();
        $ticket = Ticket::factory()->create();
        $commentaire = Commentaire::factory()->create(['ticket_id' => $ticket->id]);
        $pieceJointe = PieceJointe::factory()->create(['ticket_id' => $ticket->id]);

        Assignation::factory()->create([
            'ticket_id' => $ticket->id,
            'technicien_id' => $technicien->id,
        ]);

        Sanctum::actingAs($technicien->user);

        $this->getJson("/api/tickets/{$ticket->id}/commentaires")
            ->assertOk()
            ->assertJsonFragment(['id' => $commentaire->id]);

        $this->getJson("/api/tickets/{$ticket->id}/pieces-jointes")
            ->assertOk()
            ->assertJsonFragment(['id' => $pieceJointe->id]);
    }
}
