<?php

namespace App\Http\Controllers;

use App\Http\Resources\ClientDashboardArticleResource;
use App\Http\Resources\ClientDashboardTicketResource;
use App\Models\ArticleBase;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientDashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isClient() && $user->client, 403);

        $ticketsDuClient = Ticket::query()
            ->where('client_id', $user->client->id);

        $ticketsActifs = (clone $ticketsDuClient)
            ->whereIn('statut', ['nouveau', 'en_cours', 'en_attente'])
            ->count();

        $solutionsEnAttente = (clone $ticketsDuClient)
            ->where('statut', 'en_attente')
            ->whereHas('commentaires', fn (Builder $query) => $this->solutionEnAttente($query))
            ->count();

        $ticketsRecents = (clone $ticketsDuClient)
            ->with('categorie:id,libelle')
            ->latest('created_at')
            ->limit(4)
            ->get();

        $articlesPlusVus = ArticleBase::publies()
            ->with('categorie:id,libelle')
            ->orderByDesc('vues')
            ->limit(3)
            ->get();

        return response()->json([
            'tickets_actifs' => $ticketsActifs,
            'solutions_en_attente' => $solutionsEnAttente,
            'tickets_recents' => ClientDashboardTicketResource::collection($ticketsRecents),
            'articles_plus_vus' => ClientDashboardArticleResource::collection($articlesPlusVus),
        ]);
    }

    private function solutionEnAttente(Builder $query): Builder
    {
        return $query
            ->where('est_solution', true)
            ->whereNull('solution_validee_at')
            ->whereNull('solution_rejetee_at');
    }
}
