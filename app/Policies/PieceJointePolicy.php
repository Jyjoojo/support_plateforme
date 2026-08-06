<?php

namespace App\Policies;

use App\Models\PieceJointe;
use App\Models\User;

class PieceJointePolicy
{
    public function before(User $user): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function view(User $user, PieceJointe $pieceJointe): bool
    {
        return app(TicketPolicy::class)->view($user, $pieceJointe->ticket);
    }

    public function delete(User $user, PieceJointe $pieceJointe): bool
    {
        return $pieceJointe->ajoute_par_id === $user->id
            && ! in_array($pieceJointe->ticket->statut, ['resolu', 'ferme'], true);
    }
}
