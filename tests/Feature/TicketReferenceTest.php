<?php

namespace Tests\Feature;

use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TicketReferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_trigger_genere_des_references_sequentielles_uniques(): void
    {
        $premierTicket = Ticket::factory()->create();
        $secondTicket = Ticket::factory()->create();

        $this->assertSame((int) now()->format('Y'), $premierTicket->annee);
        $this->assertSame($premierTicket->numero + 1, $secondTicket->numero);
        $this->assertSame(
            sprintf('TK%s-%04d', now()->format('y'), $premierTicket->numero),
            (new TicketResource($premierTicket))->resolve()['reference'],
        );

        $nombreDeDoublons = DB::table('tickets')
            ->select(['annee', 'numero'])
            ->groupBy(['annee', 'numero'])
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();

        $this->assertSame(0, $nombreDeDoublons);
    }
}
