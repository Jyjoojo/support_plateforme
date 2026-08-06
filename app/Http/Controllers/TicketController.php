<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Http\Resources\TicketResource;
use App\Models\ArticleBase;
use App\Models\Client;
use App\Models\Commentaire;
use App\Models\Technicien;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketCreatedNotification;
use App\Notifications\TicketResolvedNotification;
use App\Notifications\TicketStatusChangedNotification;
use App\Services\PieceJointeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class TicketController extends Controller
{
    /**
     * GET /api/tickets/non-assignes
     * Liste des tickets ouverts encore disponibles à la prise en charge.
     */
    public function nonAssignes(Request $request): AnonymousResourceCollection
    {
        $query = Ticket::with(['client.user', 'categorie'])
            ->whereDoesntHave('assignations')
            ->whereNotIn('statut', ['resolu', 'ferme']);

        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }
        if ($request->filled('priorite')) {
            $query->where('priorite', $request->priorite);
        }
        if ($request->filled('categorie_id')) {
            $query->where('categorie_id', $request->categorie_id);
        }
        if ($request->filled('search')) {
            $query->where(fn ($q) => $q->where('titre', 'like', '%'.$request->search.'%')
                ->orWhere('description', 'like', '%'.$request->search.'%')
            );
        }

        return TicketResource::collection(
            $query->orderByRaw("CASE priorite
                WHEN 'urgente' THEN 1
                WHEN 'haute'   THEN 2
                WHEN 'normale' THEN 3
                WHEN 'basse'   THEN 4
                END")
                ->paginate(20)
        );
    }

    /**
     * GET /api/tickets
     * Liste filtrée selon le rôle :
     *   - Client    → ses propres tickets
     *   - Technicien → tickets qui lui sont assignés
     *   - Admin      → tous les tickets
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $query = Ticket::with(['client.user', 'categorie', 'assignationActive.technicien.user']);

        // Filtrage par rôle
        if ($user->isClient()) {
            $query->where('client_id', $user->client->id);
        } elseif ($user->isTechnicien()) {
            $query->whereHas('assignationActive', fn ($q) => $q->where('technicien_id', $user->technicien->id)
            );
        }
        // Admin → tous les tickets

        // Filtres query string
        if ($request->filled('assignes')) {
            $query->whereHas('assignationActive');
        }
        if ($request->filled('technicien_id')) {
            $query->whereHas('assignationActive', fn ($q) => $q->where('technicien_id', $request->technicien_id)
            );
        }
        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }
        if ($request->filled('priorite')) {
            $query->where('priorite', $request->priorite);
        }
        if ($request->filled('categorie_id')) {
            $query->where('categorie_id', $request->categorie_id);
        }
        if ($request->filled('search')) {
            $query->where(fn ($q) => $q->where('titre', 'like', '%'.$request->search.'%')
                ->orWhere('description', 'like', '%'.$request->search.'%')
            );
        }

        $tickets = $query
            ->orderByRaw("CASE statut
                WHEN 'nouveau'     THEN 1
                WHEN 'en_cours'    THEN 2
                WHEN 'en_attente'  THEN 3
                WHEN 'resolu'      THEN 4
                WHEN 'ferme'       THEN 5
                END")
            ->orderByRaw("CASE priorite
                WHEN 'urgente' THEN 1
                WHEN 'haute'   THEN 2
                WHEN 'normale' THEN 3
                WHEN 'basse'   THEN 4
                END")
            ->paginate(20);

        return TicketResource::collection($tickets);
    }

    /**
     * POST /api/tickets
     */
    public function store(StoreTicketRequest $request, PieceJointeService $pieceJointeService): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();
        $fichiers = $request->file('fichiers', []);
        unset($data['fichiers']);

        // Déterminer la source et le créateur selon le rôle
        if ($user->isClient()) {
            $data['source_creation'] = 'client';
            $data['createur_id'] = $user->client->id;
            $data['createur_type'] = Client::class;
            $data['client_id'] = $user->client->id;
        } elseif ($user->isTechnicien()) {
            $data['source_creation'] = 'technicien';
            $data['createur_id'] = $user->technicien->id;
            $data['createur_type'] = Technicien::class;
            // client_id doit être fourni dans la requête pour un ticket créé par technicien
        } else {
            // Admin
            $data['source_creation'] = 'administrateur';
            $data['createur_id'] = $user->id;
            $data['createur_type'] = User::class;
        }

        $ticket = null;

        try {
            $ticket = DB::transaction(function () use ($data, $fichiers, $pieceJointeService, $user, &$ticket) {
                $ticket = Ticket::create($data);

                foreach ($fichiers as $fichier) {
                    $pieceJointeService->enregistrer($ticket, $fichier, $user);
                }

                return $ticket;
            });
        } catch (\Throwable $exception) {
            if ($ticket) {
                Storage::disk(config('filesystems.attachments_disk'))->deleteDirectory("tickets/{$ticket->id}");
            }

            throw $exception;
        }

        // Notification à tous les admins pour un nouveau ticket
        if ($user->isClient()) {
            User::where('role', 'administrateur')->each(function ($admin) use ($ticket) {
                $admin->notify(new TicketCreatedNotification($ticket));
            });
        }

        return response()->json([
            'message' => 'Ticket créé avec succès.',
            'ticket' => new TicketResource($ticket->load(['client.user', 'categorie', 'piecesJointes'])),
        ], 201);
    }

    /**
     * GET /api/tickets/{ticket}
     */
    public function show(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('view', $ticket);

        $ticket->load([
            'client.user',
            'categorie',
            'assignationActive.technicien.user',
            'assignations.technicien.user',
            'commentaires.auteur',
            'piecesJointes',
        ]);

        return response()->json(new TicketResource($ticket));
    }

    /**
     * PUT/PATCH /api/tickets/{ticket}
     */
    public function update(UpdateTicketRequest $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('update', $ticket);

        $ancienStatut = $ticket->statut;
        $ticket->update($request->validated());

        // Notification si statut changé
        if ($ancienStatut !== $ticket->statut && $ticket->client) {
            $user = User::find($ticket->client->user_id);
            if ($user) {
                $user->notify(new TicketStatusChangedNotification($ticket, $ancienStatut));
            }
        }

        return response()->json([
            'message' => 'Ticket mis à jour.',
            'ticket' => new TicketResource($ticket->fresh()),
        ]);
    }

    /**
     * DELETE /api/tickets/{ticket}
     * Soft delete — admin seulement (géré par policy)
     */
    public function destroy(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('delete', $ticket);
        $ticket->delete();

        return response()->json(['message' => 'Ticket supprimé.']);
    }

    /** POST /api/tickets/{ticket}/mettre-en-attente */
    public function mettreEnAttente(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('update', $ticket);

        return $this->transitionner($ticket, ['en_cours'], 'en_attente', 'Ticket mis en attente.');
    }

    /** POST /api/tickets/{ticket}/reprendre */
    public function reprendre(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('update', $ticket);

        return $this->transitionner($ticket, ['en_attente'], 'en_cours', 'Traitement du ticket repris.');
    }

    /** POST /api/tickets/{ticket}/fermer */
    public function fermer(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('fermer', $ticket);

        return $this->transitionner($ticket, ['resolu'], 'ferme', 'Ticket fermé.');
    }

    /**
     * POST /api/tickets/{ticket}/reouvrir
     */
    public function reOuvrir(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('update', $ticket);

        return $this->transitionner(
            $ticket,
            ['resolu', 'ferme'],
            'en_cours',
            'Ticket réouvert.',
            ['date_resolution' => null]
        );
    }

    /** POST /api/tickets/{ticket}/confirmer-resolution */
    public function confirmerResolution(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('confirmerResolution', $ticket);

        $solution = $this->solutionEnAttente($ticket);

        DB::transaction(function () use ($request, $ticket, $solution) {
            $ticket->resoudre();
            $solution->update([
                'solution_validee_at' => now(),
                'solution_validee_par_id' => $request->user()->id,
            ]);

            ArticleBase::firstOrCreate(
                ['commentaire_solution_id' => $solution->id],
                [
                    'ticket_id' => $ticket->id,
                    'technicien_id' => $solution->auteur?->technicien?->id,
                    'categorie_id' => $ticket->categorie_id,
                    'titre' => $ticket->titre,
                    'contenu' => $solution->contenu,
                    'publie' => false,
                ]
            );
        });

        $technicien = $ticket->assignationActive?->technicien?->user;
        $technicien?->notify(new TicketResolvedNotification($ticket));

        return response()->json([
            'message' => 'Résolution confirmée. Un brouillon a été créé dans la base de connaissances.',
            'ticket' => new TicketResource($ticket->fresh()),
        ]);
    }

    /** POST /api/tickets/{ticket}/refuser-solution */
    public function refuserSolution(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('refuserSolution', $ticket);

        $data = $request->validate([
            'motif' => 'required|string|min:5|max:2000',
        ]);
        $solution = $this->solutionEnAttente($ticket);

        DB::transaction(function () use ($request, $ticket, $solution, $data) {
            $solution->update(['solution_rejetee_at' => now()]);
            Commentaire::create([
                'ticket_id' => $ticket->id,
                'auteur_id' => $request->user()->id,
                'contenu' => $data['motif'],
                'est_solution' => false,
            ]);
            $ticket->reprendre();
        });

        $ancienStatut = 'en_attente';
        $technicien = $ticket->assignationActive?->technicien?->user;
        $technicien?->notify(new TicketStatusChangedNotification($ticket, $ancienStatut));

        return response()->json([
            'message' => 'Le ticket repasse en cours de traitement.',
            'ticket' => new TicketResource($ticket->fresh()),
        ]);
    }

    private function solutionEnAttente(Ticket $ticket): Commentaire
    {
        if ($ticket->statut !== 'en_attente') {
            throw ValidationException::withMessages([
                'statut' => 'Le ticket doit être en attente de validation.',
            ]);
        }

        $solution = $ticket->commentaires()
            ->where('est_solution', true)
            ->whereNull('solution_validee_at')
            ->whereNull('solution_rejetee_at')
            ->latest('created_at')
            ->first();

        if (! $solution) {
            throw ValidationException::withMessages([
                'solution' => 'Aucune proposition de solution ne doit être validée.',
            ]);
        }

        return $solution;
    }

    private function transitionner(
        Ticket $ticket,
        array $statutsAutorises,
        string $nouveauStatut,
        string $message,
        array $attributsSupplementaires = []
    ): JsonResponse {
        if (
            ! in_array($ticket->statut, $statutsAutorises, true)
            || ! $ticket->peutTransitionnerVers($nouveauStatut)
        ) {
            throw ValidationException::withMessages([
                'statut' => "Transition impossible depuis le statut « {$ticket->statut} ».",
            ]);
        }

        $ancienStatut = $ticket->statut;
        if ($nouveauStatut === 'en_attente') {
            $ticket->mettreEnAttente();
        } elseif ($nouveauStatut === 'en_cours' && in_array($ancienStatut, ['resolu', 'ferme'], true)) {
            $ticket->rouvrir();
        } elseif ($nouveauStatut === 'en_cours') {
            $ticket->reprendre();
        } elseif ($nouveauStatut === 'ferme') {
            $ticket->fermer();
        } else {
            $ticket->update([
                'statut' => $nouveauStatut,
                ...$attributsSupplementaires,
            ]);
        }

        if ($ticket->client) {
            $client = User::find($ticket->client->user_id);
            $client?->notify(new TicketStatusChangedNotification($ticket, $ancienStatut));
        }

        return response()->json([
            'message' => $message,
            'ticket' => new TicketResource($ticket->fresh()),
        ]);
    }
}
