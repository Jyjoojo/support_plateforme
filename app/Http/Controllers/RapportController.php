<?php

namespace App\Http\Controllers;

use App\Models\ArticleBase;
use App\Models\Categorie;
use App\Models\Client;
use App\Models\Technicien;
use App\Models\Ticket;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RapportController extends Controller
{
    /** GET /api/rapports/tickets */
    public function tickets(Request $request): JsonResponse
    {
        $filtres = $this->validerFiltres($request, [
            'jours_retard' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'limite' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'granularite' => ['sometimes', Rule::in(['jour', 'semaine', 'mois'])],
        ]);

        $joursRetard = $filtres['jours_retard'] ?? 3;
        $limite = $filtres['limite'] ?? 10;
        $granularite = $filtres['granularite'] ?? 'jour';
        [$debut, $fin] = $this->periode($filtres);
        $dateLimite = now()->subDays($joursRetard);

        $requete = $this->requeteTicketsFiltree($filtres);

        $parStatut = (clone $requete)
            ->selectRaw('statut, COUNT(*) as total')
            ->groupBy('statut')
            ->pluck('total', 'statut')
            ->map(fn ($total) => (int) $total);

        $parCategorie = Categorie::query()
            ->withCount(['tickets' => fn (Builder $query) => $this->appliquerFiltresTickets($query, $filtres)])
            ->when(
                isset($filtres['categorie_id']),
                fn (Builder $query) => $query->whereKey($filtres['categorie_id'])
            )
            ->orderByDesc('tickets_count')
            ->get()
            ->map(fn (Categorie $categorie) => [
                'categorie_id' => $categorie->id,
                'libelle' => $categorie->libelle,
                'total' => $categorie->tickets_count,
            ]);

        $requeteTicketsEnRetard = (clone $requete)
            ->where('statut', 'nouveau')
            ->where('created_at', '<', $dateLimite);

        return response()->json([
            'filtres' => $this->filtresReponse($filtres, [
                'periode_debut' => $debut->toIso8601String(),
                'periode_fin' => $fin->toIso8601String(),
                'jours_retard' => $joursRetard,
                'limite' => $limite,
                'granularite' => $granularite,
            ]),
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
            'evolution' => $this->evolutionTickets($filtres, $debut, $fin, $granularite),
            'temps_resolution' => $this->tempsResolution($requete),
        ]);
    }

    /** GET /api/rapports/base-de-connaissances */
    public function baseDeConnaissances(Request $request): JsonResponse
    {
        $filtres = $this->validerFiltres($request, [
            'limite' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $limite = $filtres['limite'] ?? 10;

        $requete = ArticleBase::query()
            ->when(isset($filtres['periode_debut']), fn (Builder $q) => $q->where('created_at', '>=', $filtres['periode_debut']))
            ->when(isset($filtres['periode_fin']), fn (Builder $q) => $q->where('created_at', '<=', CarbonImmutable::parse($filtres['periode_fin'])->endOfDay()))
            ->when(isset($filtres['categorie_id']), fn (Builder $q) => $q->where('categorie_id', $filtres['categorie_id']))
            ->when(isset($filtres['technicien_id']), fn (Builder $q) => $q->where('technicien_id', $filtres['technicien_id']));

        $parCategorie = Categorie::query()
            ->withCount(['articlesBase' => function (Builder $query) use ($filtres) {
                $query
                    ->when(isset($filtres['periode_debut']), fn (Builder $q) => $q->where('created_at', '>=', $filtres['periode_debut']))
                    ->when(isset($filtres['periode_fin']), fn (Builder $q) => $q->where('created_at', '<=', CarbonImmutable::parse($filtres['periode_fin'])->endOfDay()))
                    ->when(isset($filtres['technicien_id']), fn (Builder $q) => $q->where('technicien_id', $filtres['technicien_id']));
            }])
            ->when(isset($filtres['categorie_id']), fn (Builder $q) => $q->whereKey($filtres['categorie_id']))
            ->orderByDesc('articles_base_count')
            ->get()
            ->map(fn (Categorie $categorie) => [
                'categorie_id' => $categorie->id,
                'libelle' => $categorie->libelle,
                'total' => $categorie->articles_base_count,
            ]);

        return response()->json([
            'filtres' => $this->filtresReponse($filtres, ['limite' => $limite]),
            'plus_consultes' => (clone $requete)->publies()->with('categorie')->orderByDesc('vues')->limit($limite)->get(),
            'par_etat' => [
                'publies' => (clone $requete)->where('statut_editorial', ArticleBase::STATUT_PUBLIE)->count(),
                'brouillons' => (clone $requete)->where('statut_editorial', ArticleBase::STATUT_BROUILLON)->count(),
                'en_attente_validation' => (clone $requete)->where('statut_editorial', ArticleBase::STATUT_EN_ATTENTE)->count(),
                'a_corriger' => (clone $requete)->where('statut_editorial', ArticleBase::STATUT_A_CORRIGER)->count(),
                'archives' => (clone $requete)->onlyTrashed()->count(),
            ],
            'par_categorie' => $parCategorie,
            'recemment_publies' => (clone $requete)->publies()->with('categorie')->latest('updated_at')->limit($limite)->get(),
        ]);
    }

    /** GET /api/rapports/clients */
    public function clients(Request $request): JsonResponse
    {
        $filtres = $this->validerFiltres($request, [
            'limite' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $limite = $filtres['limite'] ?? 10;

        $clients = Client::query()
            ->with('user')
            ->withCount(['tickets as tickets_ouverts' => function (Builder $query) use ($filtres) {
                $this->appliquerFiltresTickets($query, $filtres)
                    ->whereNotIn('statut', ['resolu', 'ferme']);
            }])
            ->whereHas('tickets', function (Builder $query) use ($filtres) {
                $this->appliquerFiltresTickets($query, $filtres)
                    ->whereNotIn('statut', ['resolu', 'ferme']);
            })
            ->orderByDesc('tickets_ouverts')
            ->limit($limite)
            ->get()
            ->map(fn (Client $client) => [
                'client_id' => $client->id,
                'nom_complet' => $client->nom_complet,
                'email' => $client->user->email,
                'tickets_ouverts' => $client->tickets_ouverts,
            ]);

        $nouveauxComptes = User::query()
            ->where('role', 'client')
            ->when(isset($filtres['periode_debut']), fn (Builder $q) => $q->where('created_at', '>=', $filtres['periode_debut']))
            ->when(isset($filtres['periode_fin']), fn (Builder $q) => $q->where('created_at', '<=', CarbonImmutable::parse($filtres['periode_fin'])->endOfDay()));

        return response()->json([
            'filtres' => $this->filtresReponse($filtres, ['limite' => $limite]),
            'plus_de_tickets_ouverts' => $clients,
            'nouveaux_comptes' => [
                'total' => (clone $nouveauxComptes)->count(),
                'elements' => $nouveauxComptes
                    ->latest()
                    ->limit($limite)
                    ->get(['id', 'nom', 'prenom', 'email', 'created_at']),
            ],
        ]);
    }

    /** GET /api/rapports/techniciens */
    public function techniciens(Request $request): JsonResponse
    {
        $filtres = $this->validerFiltres($request);

        $techniciens = Technicien::query()
            ->with('user')
            ->when(isset($filtres['technicien_id']), fn (Builder $q) => $q->whereKey($filtres['technicien_id']))
            ->get()
            ->map(function (Technicien $technicien) use ($filtres) {
                $tickets = Ticket::query()
                    ->whereHas('assignations', function (Builder $query) use ($technicien, $filtres) {
                        $query->where('technicien_id', $technicien->id)
                            ->when(isset($filtres['periode_debut']), fn (Builder $q) => $q->where('date_assignation', '>=', $filtres['periode_debut']))
                            ->when(isset($filtres['periode_fin']), fn (Builder $q) => $q->where('date_assignation', '<=', CarbonImmutable::parse($filtres['periode_fin'])->endOfDay()));
                    })
                    ->when(isset($filtres['categorie_id']), fn (Builder $q) => $q->where('categorie_id', $filtres['categorie_id']));

                $assignes = (clone $tickets)->count();
                $resolus = (clone $tickets)
                    ->whereNotNull('date_resolution')
                    ->when(isset($filtres['periode_debut']), fn (Builder $q) => $q->where('date_resolution', '>=', $filtres['periode_debut']))
                    ->when(isset($filtres['periode_fin']), fn (Builder $q) => $q->where('date_resolution', '<=', CarbonImmutable::parse($filtres['periode_fin'])->endOfDay()))
                    ->count();

                return [
                    'technicien_id' => $technicien->id,
                    'nom_complet' => $technicien->nom_complet,
                    'specialite' => $technicien->specialite,
                    'tickets_assignes' => $assignes,
                    'tickets_resolus' => $resolus,
                    'taux_resolution' => $assignes > 0 ? round(($resolus / $assignes) * 100, 2) : 0.0,
                ];
            })
            ->sortByDesc('tickets_assignes')
            ->values();

        return response()->json([
            'filtres' => $this->filtresReponse($filtres),
            'charge' => $techniciens,
        ]);
    }

    private function validerFiltres(Request $request, array $supplementaires = []): array
    {
        $filtres = $request->validate([
            'periode_debut' => ['sometimes', 'date'],
            'periode_fin' => ['sometimes', 'date', 'after_or_equal:periode_debut'],
            'technicien_id' => ['sometimes', 'uuid', 'exists:techniciens,id'],
            'categorie_id' => ['sometimes', 'uuid', 'exists:categories,id'],
            ...$supplementaires,
        ]);

        if (isset($filtres['periode_debut'], $filtres['periode_fin'])
            && CarbonImmutable::parse($filtres['periode_debut'])->diffInDays(CarbonImmutable::parse($filtres['periode_fin'])) > 366) {
            throw ValidationException::withMessages([
                'periode_fin' => 'La période ne peut pas dépasser 366 jours.',
            ]);
        }

        return $filtres;
    }

    private function appliquerFiltresTickets(Builder $query, array $filtres, string $colonneDate = 'created_at'): Builder
    {
        return $query
            ->when(isset($filtres['periode_debut']), fn (Builder $q) => $q->where($colonneDate, '>=', $filtres['periode_debut']))
            ->when(isset($filtres['periode_fin']), fn (Builder $q) => $q->where($colonneDate, '<=', CarbonImmutable::parse($filtres['periode_fin'])->endOfDay()))
            ->when(isset($filtres['categorie_id']), fn (Builder $q) => $q->where('categorie_id', $filtres['categorie_id']))
            ->when(isset($filtres['technicien_id']), fn (Builder $q) => $q->whereHas(
                'assignations',
                fn (Builder $assignations) => $assignations->where('technicien_id', $filtres['technicien_id'])
            ));
    }

    private function requeteTicketsFiltree(array $filtres): Builder
    {
        return $this->appliquerFiltresTickets(Ticket::query(), $filtres);
    }

    private function periode(array $filtres): array
    {
        $fin = isset($filtres['periode_fin'])
            ? CarbonImmutable::parse($filtres['periode_fin'])->endOfDay()
            : CarbonImmutable::now()->endOfDay();
        $debut = isset($filtres['periode_debut'])
            ? CarbonImmutable::parse($filtres['periode_debut'])->startOfDay()
            : $fin->subDays(29)->startOfDay();

        return [$debut, $fin];
    }

    private function evolutionTickets(array $filtres, CarbonImmutable $debut, CarbonImmutable $fin, string $granularite): Collection
    {
        $filtresPeriode = [
            ...$filtres,
            'periode_debut' => $debut,
            'periode_fin' => $fin,
        ];

        $crees = $this->appliquerFiltresTickets(Ticket::query(), $filtresPeriode)
            ->get(['created_at'])
            ->countBy(fn (Ticket $ticket) => $this->clePeriode($ticket->created_at->toImmutable(), $granularite));

        $resolus = $this->appliquerFiltresTickets(Ticket::query(), $filtresPeriode, 'date_resolution')
            ->whereNotNull('date_resolution')
            ->get(['date_resolution'])
            ->countBy(fn (Ticket $ticket) => $this->clePeriode($ticket->date_resolution->toImmutable(), $granularite));

        $curseur = $this->debutPeriode($debut, $granularite);
        $dernier = $this->debutPeriode($fin, $granularite);
        $evolution = collect();

        while ($curseur <= $dernier) {
            $cle = $this->clePeriode($curseur, $granularite);
            $evolution->push([
                'periode' => $cle,
                'crees' => $crees->get($cle, 0),
                'resolus' => $resolus->get($cle, 0),
            ]);
            $curseur = match ($granularite) {
                'semaine' => $curseur->addWeek(),
                'mois' => $curseur->addMonth(),
                default => $curseur->addDay(),
            };
        }

        return $evolution;
    }

    private function tempsResolution(Builder $requete): array
    {
        $tickets = (clone $requete)
            ->whereNotNull('date_resolution')
            ->with('categorie:id,libelle')
            ->get(['id', 'categorie_id', 'created_at', 'date_resolution']);

        $moyenne = fn (Collection $elements) => $elements->isEmpty()
            ? null
            : round($elements->avg(fn (Ticket $ticket) => $ticket->created_at->diffInMinutes($ticket->date_resolution) / 60), 2);

        return [
            'unite' => 'heures',
            'global' => $moyenne($tickets),
            'par_categorie' => $tickets
                ->groupBy('categorie_id')
                ->map(fn (Collection $elements) => [
                    'categorie_id' => $elements->first()->categorie_id,
                    'libelle' => $elements->first()->categorie?->libelle ?? 'Sans catégorie',
                    'moyenne' => $moyenne($elements),
                    'tickets_resolus' => $elements->count(),
                ])
                ->values(),
        ];
    }

    private function clePeriode(CarbonImmutable $date, string $granularite): string
    {
        return match ($granularite) {
            'semaine' => $date->startOfWeek()->format('Y-m-d'),
            'mois' => $date->startOfMonth()->format('Y-m'),
            default => $date->format('Y-m-d'),
        };
    }

    private function debutPeriode(CarbonImmutable $date, string $granularite): CarbonImmutable
    {
        return match ($granularite) {
            'semaine' => $date->startOfWeek(),
            'mois' => $date->startOfMonth(),
            default => $date->startOfDay(),
        };
    }

    private function filtresReponse(array $filtres, array $valeursParDefaut = []): array
    {
        return [...$filtres, ...$valeursParDefaut];
    }
}
