<?php

namespace Tests\Unit;

use App\Models\Statistique;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class StatistiqueTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_calculer_agrege_les_tickets_sans_acces_a_la_base_de_donnees(): void
    {
        $query = Mockery::mock();

        $query->shouldReceive('count')
            ->times(3)
            ->andReturn(8, 3, 4);
        $query->shouldReceive('where')
            ->once()
            ->with('statut', 'resolu')
            ->andReturnSelf();
        $query->shouldReceive('whereIn')
            ->once()
            ->with('statut', ['nouveau', 'en_cours', 'en_attente'])
            ->andReturnSelf();
        $query->shouldReceive('whereNotNull')
            ->once()
            ->with('date_resolution')
            ->andReturnSelf();
        $query->shouldReceive('selectRaw')
            ->once()
            ->with('AVG(EXTRACT(EPOCH FROM (date_resolution - created_at)) / 3600) as moyenne')
            ->andReturnSelf();
        $query->shouldReceive('value')
            ->once()
            ->with('moyenne')
            ->andReturn(5.5);

        $statistique = Statistique::calculer(
            genereParId: 'utilisateur-1',
            query: $query,
            enregistrer: false
        );

        $this->assertFalse($statistique->exists);
        $this->assertSame(8, $statistique->total_tickets);
        $this->assertSame(3, $statistique->tickets_resolus);
        $this->assertSame(4, $statistique->tickets_en_cours);
        $this->assertSame(5.5, $statistique->temps_moyen_resolution);
    }
}
