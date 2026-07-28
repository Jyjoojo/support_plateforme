<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SanctumApiAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_login_retourne_un_token_sanctum_valide(): void
    {
        $user = User::factory()->client()->create([
            'actif' => true,
            'password' => bcrypt('mot-de-passe-test'),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'mot-de-passe-test',
        ])->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'email']]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'api-token',
        ]);
    }

    public function test_un_endpoint_protege_refuse_une_requete_sans_token(): void
    {
        $this->getJson('/api/auth/me')
            ->assertUnauthorized();
    }

    public function test_un_client_authentifie_est_refuse_sur_une_route_technicien(): void
    {
        $client = Client::factory()->create();

        $token = $client->user
            ->createToken('test-client', ['client'])
            ->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/tickets/non-assignes')
            ->assertForbidden();
    }
}
