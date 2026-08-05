<?php

namespace Tests\Unit;

use App\Models\Ticket;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TicketStatusTransitionTest extends TestCase
{
    #[DataProvider('transitionsProvider')]
    public function test_les_transitions_de_statut_respectent_le_workflow(
        string $statutActuel,
        string $nouveauStatut,
        bool $estAutorisee
    ): void {
        $ticket = new Ticket(['statut' => $statutActuel]);

        $this->assertSame(
            $estAutorisee,
            $ticket->peutTransitionnerVers($nouveauStatut)
        );
    }

    public static function transitionsProvider(): array
    {
        return [
            'nouveau vers en cours' => ['nouveau', 'en_cours', true],
            'nouveau vers resolu interdit' => ['nouveau', 'resolu', false],
            'en cours vers en attente' => ['en_cours', 'en_attente', true],
            'en cours vers resolu interdit' => ['en_cours', 'resolu', false],
            'resolu vers ferme' => ['resolu', 'ferme', true],
            'statut inconnu' => ['inconnu', 'en_cours', false],
        ];
    }
}
