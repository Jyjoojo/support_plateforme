<?php

namespace App\Policies;

use App\Models\ArticleBase;
use App\Models\User;

class ArticleBasePolicy
{
    public function view(User $user, ArticleBase $article): bool
    {
        if ($user->isAdmin() || $user->isTechnicien()) {
            return true;
        }

        return $user->isClient()
            && $user->client
            && $article->ticket
            && $article->ticket->client_id === $user->client->id
            && $article->commentaireSolution?->solution_validee_at !== null;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isTechnicien();
    }

    public function update(User $user, ArticleBase $article): bool
    {
        return $user->isAdmin()
            || ($user->isTechnicien()
                && $article->auteur_id === $user->id
                && $article->estEditableParTechnicien());
    }

    public function submit(User $user, ArticleBase $article): bool
    {
        return $user->isTechnicien()
            && $article->auteur_id === $user->id
            && $article->estEditableParTechnicien();
    }

    public function review(User $user, ArticleBase $article): bool
    {
        return $user->isAdmin();
    }

    public function archive(User $user, ArticleBase $article): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, ArticleBase $article): bool
    {
        return $user->isAdmin() && $article->trashed();
    }
}
