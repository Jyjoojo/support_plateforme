<?php

namespace Tests\Feature;

use App\Http\Resources\ArticleBaseResource;
use App\Models\ArticleBase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ArticleReferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_trigger_genere_des_references_annuelles_sequentielles_uniques(): void
    {
        $premierArticle = ArticleBase::factory()->create();
        $secondArticle = ArticleBase::factory()->create();

        $this->assertSame((int) now()->format('Y'), $premierArticle->annee);
        $this->assertSame($premierArticle->numero + 1, $secondArticle->numero);
        $this->assertSame(
            sprintf('KB-%s-%04d', now()->format('Y'), $premierArticle->numero),
            $premierArticle->reference,
        );
        $this->assertSame(
            $premierArticle->reference,
            (new ArticleBaseResource($premierArticle))->resolve()['reference'],
        );

        $doublons = ArticleBase::query()
            ->select(['annee', 'numero'])
            ->groupBy(['annee', 'numero'])
            ->havingRaw('COUNT(*) > 1')
            ->count();

        $this->assertSame(0, $doublons);
    }

    public function test_la_reference_est_exposee_a_la_creation_api(): void
    {
        $admin = User::factory()->administrateur()->create();
        Sanctum::actingAs($admin);

        $this->postJson('/api/articles', [
            'titre' => 'Article référencé',
            'contenu' => 'Contenu de la procédure.',
        ])
            ->assertCreated()
            ->assertJsonPath('article.reference', fn (string $reference) => preg_match('/^KB-\d{4}-\d{4}$/', $reference) === 1);
    }
}
