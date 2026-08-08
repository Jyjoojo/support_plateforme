<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\SystemMessageNotification;
use App\Notifications\TicketAssignedNotification;
use App\Notifications\TicketCommentedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_liste_retourne_des_notifications_pretes_a_afficher(): void
    {
        $user = User::factory()->create();
        $ticketId = (string) Str::uuid();

        $this->createNotification($user, TicketAssignedNotification::class, [
            'ticket_id' => $ticketId,
            'ticket_reference' => 'TK26-0042',
            'ticket_titre' => 'Accès à la messagerie',
            'assignation_id' => (string) Str::uuid(),
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('total_non_lues', 1)
            ->assertJsonPath('data.0.type', TicketAssignedNotification::class)
            ->assertJsonPath('data.0.categorie', 'ticket')
            ->assertJsonPath('data.0.titre', 'Ticket assigné')
            ->assertJsonPath(
                'data.0.contenu',
                'Le ticket TK26-0042 « Accès à la messagerie » vous a été assigné.',
            )
            ->assertJsonPath('data.0.ticket_id', $ticketId)
            ->assertJsonPath('data.0.action.type', 'ouvrir_ticket')
            ->assertJsonPath('data.0.action.ticket_id', $ticketId)
            ->assertJsonPath('data.0.lu', false)
            ->assertJsonStructure([
                'data',
                'links',
                'meta',
                'total_non_lues',
            ]);
    }

    public function test_le_front_peut_recuperer_les_categories_et_filtrer_la_liste(): void
    {
        $user = User::factory()->create();

        $this->createNotification($user, TicketAssignedNotification::class, [
            'ticket_id' => (string) Str::uuid(),
        ]);
        $this->createNotification($user, SystemMessageNotification::class, [
            'title' => 'Maintenance',
            'message' => 'Une maintenance est prévue.',
        ], now()->addSecond());

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/notifications/categories')
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    ['valeur' => 'ticket', 'libelle' => 'Tickets'],
                    ['valeur' => 'systeme', 'libelle' => 'Système'],
                ],
            ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/notifications?categorie=systeme')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', SystemMessageNotification::class)
            ->assertJsonPath('data.0.categorie', 'systeme');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/notifications?categorie=inconnue')
            ->assertUnprocessable();
    }

    public function test_messages_retourne_uniquement_les_cinq_derniers_commentaires(): void
    {
        $user = User::factory()->create();

        $this->createNotification($user, TicketAssignedNotification::class, [
            'ticket_id' => (string) Str::uuid(),
        ], now()->addMinute());

        for ($index = 1; $index <= 6; $index++) {
            $this->createNotification($user, TicketCommentedNotification::class, [
                'ticket_id' => (string) Str::uuid(),
                'ticket_reference' => "TK26-000{$index}",
                'ticket_titre' => "Ticket {$index}",
                'auteur_nom' => 'Jean Dupont',
                'excerpt' => "Commentaire {$index}",
            ], now()->addSeconds($index));
        }

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/notifications/messages')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('data.0.type', TicketCommentedNotification::class)
            ->assertJsonPath('data.0.titre', 'Nouveau commentaire')
            ->assertJsonPath(
                'data.0.contenu',
                'Jean Dupont a ajouté un commentaire sur le ticket TK26-0006 « Ticket 6 ». « Commentaire 6 »',
            );

        foreach ($response->json('data') as $notification) {
            $this->assertSame(TicketCommentedNotification::class, $notification['type']);
        }
    }

    /** @param array<string, mixed> $data */
    private function createNotification(
        User $user,
        string $type,
        array $data,
        mixed $createdAt = null,
    ): void {
        $createdAt ??= now();

        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => $type,
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode($data, JSON_THROW_ON_ERROR),
            'read_at' => null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
