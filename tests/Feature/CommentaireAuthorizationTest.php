<?php

namespace Tests\Feature;

use App\Models\Assignation;
use App\Models\Client;
use App\Models\Commentaire;
use App\Models\Technicien;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommentaireAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_client_peut_modifier_son_propre_commentaire(): void
    {
        $client = Client::factory()->create();
        $ticket = Ticket::factory()->create(['client_id' => $client->id]);
        $commentaire = Commentaire::factory()->create([
            'ticket_id' => $ticket->id,
            'auteur_id' => $client->user_id,
        ]);

        Sanctum::actingAs($client->user);

        $this->patchJson("/api/tickets/{$ticket->id}/commentaires/{$commentaire->id}", [
            'contenu' => 'Commentaire corrigé.',
        ])->assertOk()
            ->assertJsonPath('commentaire.contenu', 'Commentaire corrigé.');
    }

    public function test_un_technicien_assigne_peut_modifier_son_propre_commentaire(): void
    {
        $technicien = Technicien::factory()->create();
        $ticket = Ticket::factory()->create();
        Assignation::factory()->create([
            'ticket_id' => $ticket->id,
            'technicien_id' => $technicien->id,
            'date_assignation' => now(),
        ]);
        $commentaire = Commentaire::factory()->create([
            'ticket_id' => $ticket->id,
            'auteur_id' => $technicien->user_id,
        ]);

        Sanctum::actingAs($technicien->user);

        $this->patchJson("/api/tickets/{$ticket->id}/commentaires/{$commentaire->id}", [
            'contenu' => 'Diagnostic corrigé.',
        ])->assertOk();
    }

    public function test_un_utilisateur_ne_peut_pas_modifier_le_commentaire_d_un_autre(): void
    {
        $client = Client::factory()->create();
        $ticket = Ticket::factory()->create(['client_id' => $client->id]);
        $commentaire = Commentaire::factory()->create([
            'ticket_id' => $ticket->id,
            'auteur_id' => User::factory()->create()->id,
        ]);

        Sanctum::actingAs($client->user);

        $this->patchJson("/api/tickets/{$ticket->id}/commentaires/{$commentaire->id}", [
            'contenu' => 'Modification interdite.',
        ])->assertForbidden();
    }

    public function test_un_commentaire_ne_peut_pas_etre_modifie_via_un_autre_ticket(): void
    {
        $client = Client::factory()->create();
        $ticket = Ticket::factory()->create(['client_id' => $client->id]);
        $autreTicket = Ticket::factory()->create(['client_id' => $client->id]);
        $commentaire = Commentaire::factory()->create([
            'ticket_id' => $ticket->id,
            'auteur_id' => $client->user_id,
        ]);

        Sanctum::actingAs($client->user);

        $this->patchJson("/api/tickets/{$autreTicket->id}/commentaires/{$commentaire->id}", [
            'contenu' => 'Modification interdite.',
        ])->assertNotFound();
    }

    public function test_l_auteur_ne_peut_pas_supprimer_un_commentaire_solution(): void
    {
        $client = Client::factory()->create();
        $ticket = Ticket::factory()->create(['client_id' => $client->id]);
        $commentaire = Commentaire::factory()->create([
            'ticket_id' => $ticket->id,
            'auteur_id' => $client->user_id,
            'est_solution' => true,
        ]);

        Sanctum::actingAs($client->user);

        $this->deleteJson("/api/tickets/{$ticket->id}/commentaires/{$commentaire->id}")
            ->assertForbidden();
    }
}
