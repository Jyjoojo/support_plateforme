<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\PieceJointe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PieceJointeWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        config(['filesystems.attachments_disk' => 'private']);
    }

    public function test_un_client_peut_creer_un_ticket_sans_piece_jointe(): void
    {
        $client = Client::factory()->create();
        Sanctum::actingAs($client->user);

        $this->postJson('/api/tickets', [
            'titre' => 'Problème sans capture',
            'description' => 'Description suffisamment détaillée du problème.',
        ])->assertCreated()->assertJsonCount(0, 'ticket.pieces_jointes');

        $this->assertDatabaseCount('piece_jointes', 0);
    }

    public function test_un_client_peut_creer_un_ticket_avec_plusieurs_pieces_jointes(): void
    {
        $client = Client::factory()->create();
        Sanctum::actingAs($client->user);

        $response = $this->post('/api/tickets', [
            'titre' => 'Problème avec captures',
            'description' => 'Description suffisamment détaillée du problème.',
            'fichiers' => [
                UploadedFile::fake()->create('capture.png', 100, 'image/png'),
                UploadedFile::fake()->create('rapport.pdf', 200, 'application/pdf'),
            ],
        ], ['Accept' => 'application/json']);

        $response->assertCreated()->assertJsonCount(2, 'ticket.pieces_jointes');
        $this->assertDatabaseCount('piece_jointes', 2);

        foreach (PieceJointe::all() as $pieceJointe) {
            Storage::disk('private')->assertExists($pieceJointe->chemin_fichier);
            $this->assertSame($client->user_id, $pieceJointe->ajoute_par_id);
        }
    }

    public function test_une_archive_zip_est_refusee(): void
    {
        $client = Client::factory()->create();
        Sanctum::actingAs($client->user);

        $this->post('/api/tickets', [
            'titre' => 'Ticket avec archive',
            'description' => 'Description suffisamment détaillée du problème.',
            'fichiers' => [UploadedFile::fake()->create('archive.zip', 100, 'application/zip')],
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fichiers.0');
    }

    public function test_le_proprietaire_peut_afficher_telecharger_et_supprimer_sa_piece_jointe(): void
    {
        $client = Client::factory()->create();
        Sanctum::actingAs($client->user);
        $fichier = UploadedFile::fake()->create('capture.jpg', 100, 'image/jpeg');

        $ticketId = $this->post('/api/tickets', [
            'titre' => 'Ticket avec image',
            'description' => 'Description suffisamment détaillée du problème.',
            'fichiers' => [$fichier],
        ], ['Accept' => 'application/json'])->json('ticket.id');

        $pieceJointe = PieceJointe::where('ticket_id', $ticketId)->firstOrFail();

        $affichage = $this->get("/api/pieces-jointes/{$pieceJointe->id}/afficher")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
        $telechargement = $this->get("/api/pieces-jointes/{$pieceJointe->id}/telecharger")
            ->assertOk();

        $this->assertStringStartsWith('inline;', $affichage->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('attachment;', $telechargement->headers->get('Content-Disposition'));
        $this->deleteJson("/api/pieces-jointes/{$pieceJointe->id}")->assertOk();

        Storage::disk('private')->assertMissing($pieceJointe->chemin_fichier);
        $this->assertDatabaseMissing('piece_jointes', ['id' => $pieceJointe->id]);
    }
}
