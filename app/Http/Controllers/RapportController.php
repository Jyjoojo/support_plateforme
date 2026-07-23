<?php

namespace App\Http\Controllers;

use App\Models\ArticleBase;
use App\Models\Categorie;
use App\Models\Client;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RapportController extends Controller
{
    /** GET /api/rapports/tickets */
    public function tickets(Request $request): JsonResponse
    {
        $filtres = $request->validate([
            'jours_retard' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'limite' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $joursRetard = $filtres['jours_retard'] ?? 3;
        $limite = $filtres['limite'] ?? 10;
        $dateLimite = now()->subDays($joursRetard);

        $parStatut = Ticket::query()
            ->selectRaw('statut, COUNT(*) as total')
            ->groupBy('statut')
            ->pluck('total', 'statut')
            ->map(fn ($total) => (int) $total);

        $parCategorie = Categorie::query()
            ->leftJoin('tickets', 'categories.id', '=', 'tickets.categorie_id')
            ->selectRaw('categories.id, categories.libelle, COUNT(tickets.id) as total')
            ->groupBy('categories.id', 'categories.libelle')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($categorie) => [
                'categorie_id' => $categorie->id,
                'libelle' => $categorie->libelle,
                'total' => (int) $categorie->total,
            ]);

        $requeteTicketsEnRetard = Ticket::query()
            ->where('statut', 'nouveau')
            ->where('created_at', '<', $dateLimite);

        return response()->json([
            'filtres' => [
                'jours_retard' => $joursRetard,
                'limite' => $limite,
            ],
            'par_statut' => $parStatut,
            'par_categorie' => $parCategorie,
            'en_retard' => [
                'date_limite' => $dateLimite->toIso8601String(),
                'total' => (clone $requeteTicketsEnRetard)->count(),
                'elements' => $requeteTicketsEnRetard
                    ->with(['client.user', 'categorie'])
                    ->oldest()
                    ->limit($limite)
                    ->get(),
            ],
        ]);
    }

    /** GET /api/rapports/base-de-connaissances */
    public function baseDeConnaissances(Request $request): JsonResponse
    {
        $filtres = $request->validate([
            'limite' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $limite = $filtres['limite'] ?? 10;

        $parCategorie = Categorie::query()
            ->leftJoin('article_bases', function ($join) {
                $join->on('categories.id', '=', 'article_bases.categorie_id')
                    ->whereNull('article_bases.deleted_at');
            })
            ->selectRaw('categories.id, categories.libelle, COUNT(article_bases.id) as total')
            ->groupBy('categories.id', 'categories.libelle')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($categorie) => [
                'categorie_id' => $categorie->id,
                'libelle' => $categorie->libelle,
                'total' => (int) $categorie->total,
            ]);

        return response()->json([
            'filtres' => ['limite' => $limite],
            'plus_consultes' => ArticleBase::query()
                ->publies()
                ->with('categorie')
                ->orderByDesc('vues')
                ->limit($limite)
                ->get(),
            'par_etat' => [
                'publies' => ArticleBase::query()->where('publie', true)->count(),
                'brouillons' => ArticleBase::query()->where('publie', false)->count(),
                'archives' => ArticleBase::onlyTrashed()->count(),
            ],
            'par_categorie' => $parCategorie,
            'recemment_publies' => ArticleBase::query()
                ->publies()
                ->with('categorie')
                ->latest('updated_at')
                ->limit($limite)
                ->get(),
        ]);
    }

    /** GET /api/rapports/clients */
    public function clients(Request $request): JsonResponse
    {
        $filtres = $request->validate([
            'limite' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $limite = $filtres['limite'] ?? 10;

        $clientsAvecTicketsOuverts = Client::query()
            ->join('users', 'clients.user_id', '=', 'users.id')
            ->join('tickets', 'clients.id', '=', 'tickets.client_id')
            ->whereNotIn('tickets.statut', ['resolu', 'ferme'])
            ->selectRaw('clients.id, users.nom, users.prenom, users.email, COUNT(tickets.id) as total')
            ->groupBy('clients.id', 'users.nom', 'users.prenom', 'users.email')
            ->orderByDesc('total')
            ->limit($limite)
            ->get()
            ->map(fn ($client) => [
                'client_id' => $client->id,
                'nom_complet' => trim($client->prenom.' '.$client->nom),
                'email' => $client->email,
                'tickets_ouverts' => (int) $client->total,
            ]);

        return response()->json([
            'filtres' => ['limite' => $limite],
            'plus_de_tickets_ouverts' => $clientsAvecTicketsOuverts,
            'nouveaux_comptes' => [
                'total' => User::query()->where('role', 'client')->count(),
                'elements' => User::query()
                    ->where('role', 'client')
                    ->latest()
                    ->limit($limite)
                    ->get(['id', 'nom', 'prenom', 'email', 'created_at']),
            ],
        ]);
    }
}
