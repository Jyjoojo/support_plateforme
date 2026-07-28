<?php

namespace Tests\Feature;

use App\Models\ArticleBase;
use App\Models\Technicien;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ArticleApiFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_liste_publique_ne_retourne_que_les_articles_publies_non_archives(): void
    {
        $publie = ArticleBase::factory()->create(['publie' => true]);
        $brouillon = ArticleBase::factory()->create(['publie' => false]);
        $archive = ArticleBase::factory()->create(['publie' => true]);
        $archive->delete();

        $this->getJson('/api/articles')
            ->assertOk()
            ->assertJsonFragment(['id' => $publie->id])
            ->assertJsonMissing(['id' => $brouillon->id])
            ->assertJsonMissing(['id' => $archive->id]);
    }

    public function test_un_technicien_peut_filtrer_les_brouillons(): void
    {
        $technicien = Technicien::factory()->create();
        $publie = ArticleBase::factory()->create(['publie' => true]);
        $brouillon = ArticleBase::factory()->create(['publie' => false]);
        Sanctum::actingAs($technicien->user);

        $this->getJson('/api/articles?publie=0')
            ->assertOk()
            ->assertJsonFragment(['id' => $brouillon->id])
            ->assertJsonMissing(['id' => $publie->id]);
    }

    public function test_le_filtre_with_archived_inclut_les_articles_archives(): void
    {
        $technicien = Technicien::factory()->create();
        $actif = ArticleBase::factory()->create();
        $archive = ArticleBase::factory()->create();
        $archive->delete();
        Sanctum::actingAs($technicien->user);

        $this->getJson('/api/articles?with_archived=1')
            ->assertOk()
            ->assertJsonFragment(['id' => $actif->id])
            ->assertJsonFragment(['id' => $archive->id]);
    }

    public function test_le_filtre_only_archived_exclut_les_articles_actifs(): void
    {
        $technicien = Technicien::factory()->create();
        $actif = ArticleBase::factory()->create();
        $archive = ArticleBase::factory()->create();
        $archive->delete();
        Sanctum::actingAs($technicien->user);

        $this->getJson('/api/articles?only_archived=1')
            ->assertOk()
            ->assertJsonFragment(['id' => $archive->id])
            ->assertJsonMissing(['id' => $actif->id]);
    }
}
