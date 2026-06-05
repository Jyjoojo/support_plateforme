<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TicketPolicy
{
    /** Admin peut tout faire */
    public function before(User $user): ?bool
    {
        if ($user->isAdmin()) return true;
        return null; // continue vers les méthodes spécifiques
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Ticket $ticket): bool
    {
        if ($user->isTechnicien()) {
            // Le technicien voit le ticket s'il y est assigné
            return $ticket->assignations()
                ->where('technicien_id', $user->technicien->id)
                ->exists();
        }

        if ($user->isClient()) {
            return $ticket->client_id === $user->client->id;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true; // Tout le monde peut créer (client, technicien, admin)
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Ticket $ticket): bool
    {
        if ($user->isTechnicien()) {
            return $ticket->assignations()
                ->where('technicien_id', $user->technicien->id)
                ->exists();
        }

        if ($user->isClient()) {
            // Le client peut modifier seulement si le ticket est nouveau
            return $ticket->client_id === $user->client->id
                && $ticket->statut === 'nouveau';
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Ticket $ticket): bool
    {
        return false; // seul l'admin peut (before() retourne true pour admin)
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Ticket $ticket): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Ticket $ticket): bool
    {
        return false;
    }

    /** Assigner un technicien à un ticket */
    public function assigner(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin() || $user->isTechnicien();
    }
}
