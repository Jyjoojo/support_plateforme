<?php

namespace App\Policies;

use App\Models\Commentaire;
use App\Models\User;

class CommentairePolicy
{
    public function before(User $user): ?bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return null;
    }

    public function update(User $user, Commentaire $commentaire): bool
    {
        return $this->estAuteurAvecAccesAuTicket($user, $commentaire);
    }

    public function delete(User $user, Commentaire $commentaire): bool
    {
        return !$commentaire->est_solution
            && $this->estAuteurAvecAccesAuTicket($user, $commentaire);
    }

    private function estAuteurAvecAccesAuTicket(User $user, Commentaire $commentaire): bool
    {
        return $commentaire->auteur_id === $user->id
            && $commentaire->ticket !== null
            && $user->can('view', $commentaire->ticket);
    }
}
