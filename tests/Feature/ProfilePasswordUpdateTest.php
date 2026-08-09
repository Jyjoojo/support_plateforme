<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfilePasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_utilisateur_peut_modifier_son_mot_de_passe_avec_confirmation(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('ancien-mot-de-passe'),
        ]);
        Sanctum::actingAs($user);

        $this->patchJson('/api/auth/profil', [
            'ancien_mot_de_passe' => 'ancien-mot-de-passe',
            'nouveau_mot_de_passe' => 'Nouveau-mot-de-passe1',
            'confirmation_mot_de_passe' => 'Nouveau-mot-de-passe1',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Profil mis à jour.');

        $motDePasseEnregistre = $user->fresh()->password;

        $this->assertTrue(Hash::check('Nouveau-mot-de-passe1', $motDePasseEnregistre));
        $this->assertFalse(Hash::check('ancien-mot-de-passe', $motDePasseEnregistre));
    }

    public function test_la_confirmation_du_nouveau_mot_de_passe_doit_correspondre(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('ancien-mot-de-passe'),
        ]);
        Sanctum::actingAs($user);

        $this->patchJson('/api/auth/profil', [
            'ancien_mot_de_passe' => 'ancien-mot-de-passe',
            'nouveau_mot_de_passe' => 'nouveau-mot-de-passe',
            'confirmation_mot_de_passe' => 'mot-de-passe-different',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('confirmation_mot_de_passe');

        $this->assertTrue(Hash::check('ancien-mot-de-passe', $user->fresh()->password));
    }

    public function test_le_nouveau_mot_de_passe_respecte_les_regles_de_complexite(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Ancien-mot-de-passe1'),
        ]);
        Sanctum::actingAs($user);

        $motsDePasseInvalides = [
            'Court1A',              // Moins de 8 caractères
            'motdepasse1',          // Sans majuscule
            'MOTDEPASSE1',          // Sans minuscule
            'MotDePasse',           // Sans chiffre
        ];

        foreach ($motsDePasseInvalides as $motDePasse) {
            $this->patchJson('/api/auth/profil', [
                'ancien_mot_de_passe' => 'Ancien-mot-de-passe1',
                'nouveau_mot_de_passe' => $motDePasse,
                'confirmation_mot_de_passe' => $motDePasse,
            ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('nouveau_mot_de_passe');
        }

        $this->assertTrue(Hash::check('Ancien-mot-de-passe1', $user->fresh()->password));
    }

    public function test_l_ancien_mot_de_passe_doit_etre_correct(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Ancien-mot-de-passe1'),
        ]);
        Sanctum::actingAs($user);

        $this->patchJson('/api/auth/profil', [
            'ancien_mot_de_passe' => 'Mauvais-mot-de-passe1',
            'nouveau_mot_de_passe' => 'Nouveau-mot-de-passe2',
            'confirmation_mot_de_passe' => 'Nouveau-mot-de-passe2',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ancien_mot_de_passe');

        $this->assertTrue(Hash::check('Ancien-mot-de-passe1', $user->fresh()->password));
    }
}
