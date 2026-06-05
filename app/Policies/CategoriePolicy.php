<?php

namespace App\Policies;

use App\Models\Categorie;
use App\Models\User;

class CategoriePolicy
{
    public function before(User $user): ?bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Categorie $categorie): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return false; // Seul l'admin peut créer une catégorie
    }

    public function update(User $user, Categorie $categorie): bool
    {
        return false; // Seul l'admin peut modifier une catégorie
    }

    public function delete(User $user, Categorie $categorie): bool
    {
        return false; // Seul l'admin peut supprimer une catégorie
    }

    public function restore(User $user, Categorie $categorie): bool
    {
        return false;
    }

    public function forceDelete(User $user, Categorie $categorie): bool
    {
        return false;
    }
}
