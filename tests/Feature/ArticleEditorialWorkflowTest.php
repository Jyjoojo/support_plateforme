<?php

namespace Tests\Feature;

use App\Models\ArticleBase;
use App\Models\Technicien;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ArticleEditorialWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_administrateur_est_enregistre_comme_auteur_de_son_article(): void
    {
        $admin = User::factory()->administrateur()->create();
        Sanctum::actingAs($admin);

        $this->postJson('/api/articles', [
            'titre' => 'Procédure administrateur',
            'contenu' => 'Contenu complet de la procédure.',
        ])
            ->assertCreated()
            ->assertJsonPath('article.auteur.id', $admin->id)
            ->assertJsonPath('article.auteur.role', 'administrateur')
            ->assertJsonPath('article.statut_editorial', ArticleBase::STATUT_BROUILLON);

        $this->assertDatabaseHas('article_bases', [
            'auteur_id' => $admin->id,
            'technicien_id' => null,
            'statut_editorial' => ArticleBase::STATUT_BROUILLON,
        ]);
    }

    public function test_le_technicien_soumet_et_l_administrateur_valide_l_article(): void
    {
        $technicien = Technicien::factory()->create();
        $admin = User::factory()->administrateur()->create();
        Sanctum::actingAs($technicien->user);

        $articleId = $this->postJson('/api/articles', [
            'titre' => 'Réinitialiser un accès',
            'contenu' => 'Étapes de réinitialisation.',
        ])->assertCreated()->json('article.id');

        $this->postJson("/api/articles/{$articleId}/soumettre")
            ->assertOk()
            ->assertJsonPath('article.statut_editorial', ArticleBase::STATUT_EN_ATTENTE)
            ->assertJsonPath('article.soumis_par.id', $technicien->user->id);

        $this->patchJson("/api/articles/{$articleId}", ['titre' => 'Modification interdite'])
            ->assertForbidden();
        $this->postJson("/api/articles/{$articleId}/valider")->assertForbidden();

        Sanctum::actingAs($admin);
        $this->postJson("/api/articles/{$articleId}/valider")
            ->assertOk()
            ->assertJsonPath('article.statut_editorial', ArticleBase::STATUT_PUBLIE)
            ->assertJsonPath('article.publie', true)
            ->assertJsonPath('article.valide_par.id', $admin->id);

        $this->assertNotNull(ArticleBase::findOrFail($articleId)->publie_at);
    }

    public function test_l_administrateur_peut_refuser_et_le_technicien_corriger_puis_resoumettre(): void
    {
        $technicien = Technicien::factory()->create();
        $admin = User::factory()->administrateur()->create();
        $article = ArticleBase::factory()->create([
            'auteur_id' => $technicien->user->id,
            'technicien_id' => $technicien->id,
            'publie' => false,
            'statut_editorial' => ArticleBase::STATUT_EN_ATTENTE,
        ]);

        Sanctum::actingAs($admin);
        $this->postJson("/api/articles/{$article->id}/refuser", [
            'motif' => 'Ajouter les prérequis techniques.',
        ])
            ->assertOk()
            ->assertJsonPath('article.statut_editorial', ArticleBase::STATUT_A_CORRIGER)
            ->assertJsonPath('article.motif_refus', 'Ajouter les prérequis techniques.');

        Sanctum::actingAs($technicien->user);
        $this->patchJson("/api/articles/{$article->id}", [
            'contenu' => 'Prérequis ajoutés et procédure corrigée.',
        ])->assertOk();

        $this->postJson("/api/articles/{$article->id}/soumettre")
            ->assertOk()
            ->assertJsonPath('article.statut_editorial', ArticleBase::STATUT_EN_ATTENTE)
            ->assertJsonPath('article.motif_refus', null);
    }

    public function test_un_technicien_ne_peut_modifier_que_ses_brouillons_editables(): void
    {
        $technicien = Technicien::factory()->create();
        $autreTechnicien = Technicien::factory()->create();
        $articleAutre = ArticleBase::factory()->create([
            'auteur_id' => $autreTechnicien->user->id,
            'technicien_id' => $autreTechnicien->id,
            'publie' => false,
            'statut_editorial' => ArticleBase::STATUT_BROUILLON,
        ]);

        Sanctum::actingAs($technicien->user);
        $this->patchJson("/api/articles/{$articleAutre->id}", ['titre' => 'Intrusion'])
            ->assertForbidden();
    }

    public function test_l_administrateur_archive_puis_restaure_un_article_comme_brouillon(): void
    {
        $admin = User::factory()->administrateur()->create();
        $article = ArticleBase::factory()->create([
            'publie' => true,
            'statut_editorial' => ArticleBase::STATUT_PUBLIE,
        ]);
        Sanctum::actingAs($admin);

        $this->postJson("/api/articles/{$article->id}/archiver")
            ->assertOk()
            ->assertJsonPath('article.statut_editorial', ArticleBase::STATUT_ARCHIVE)
            ->assertJsonPath('article.archive_par.id', $admin->id);
        $this->assertSoftDeleted('article_bases', ['id' => $article->id]);

        $this->postJson("/api/articles/{$article->id}/restaurer")
            ->assertOk()
            ->assertJsonPath('article.statut_editorial', ArticleBase::STATUT_BROUILLON)
            ->assertJsonPath('article.publie', false)
            ->assertJsonPath('article.archive_at', null);

        $this->assertDatabaseHas('article_bases', [
            'id' => $article->id,
            'deleted_at' => null,
            'statut_editorial' => ArticleBase::STATUT_BROUILLON,
            'publie' => false,
        ]);
    }

    public function test_un_brouillon_est_visible_aux_equipes_mais_pas_au_public(): void
    {
        $technicien = Technicien::factory()->create();
        $brouillon = ArticleBase::factory()->create([
            'publie' => false,
            'statut_editorial' => ArticleBase::STATUT_BROUILLON,
        ]);

        Sanctum::actingAs($technicien->user);
        $this->getJson("/api/articles/{$brouillon->id}")
            ->assertOk()
            ->assertJsonPath('statut_editorial', ArticleBase::STATUT_BROUILLON);
    }

    public function test_un_brouillon_n_est_pas_visible_sans_authentification(): void
    {
        $brouillon = ArticleBase::factory()->create([
            'publie' => false,
            'statut_editorial' => ArticleBase::STATUT_BROUILLON,
        ]);

        $this->getJson("/api/articles/{$brouillon->id}")->assertNotFound();
    }
}
