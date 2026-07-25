<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\User;
use App\Models\Technicien;
use App\Models\Client;
use App\Notifications\TicketCreatedNotification;
use App\Notifications\TicketStatusChangedNotification;

use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use App\Http\Resources\TicketResource;
use Illuminate\Support\Facades\Gate;
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
            $query->where(fn($q) =>
                $q->where('titre', 'like', '%' . $request->search . '%')
                    ->orWhere('description', 'like', '%' . $request->search . '%')
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
        $user  = $request->user();
        $query = Ticket::with(['client.user', 'categorie', 'assignationActive.technicien.user']);

        // Filtrage par rôle
        if ($user->isClient()) {
            $query->where('client_id', $user->client->id);
        } elseif ($user->isTechnicien()) {
            $query->whereHas('assignationActive', fn($q) =>
                $q->where('technicien_id', $user->technicien->id)
            );
        }
        // Admin → tous les tickets

        // Filtres query string
        if ($request->filled('assignes')) {
            $query->whereHas('assignationActive');
        }
        if ($request->filled('technicien_id')) {
            $query->whereHas('assignationActive', fn($q) =>
                $q->where('technicien_id', $request->technicien_id)
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
            $query->where(fn($q) =>
                $q->where('titre', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%')
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
    public function store(StoreTicketRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        // Déterminer la source et le créateur selon le rôle
        if ($user->isClient()) {
            $data['source_creation'] = 'client';
            $data['createur_id']     = $user->client->id;
            $data['createur_type']   = Client::class;
            $data['client_id']       = $user->client->id;
        } elseif ($user->isTechnicien()) {
            $data['source_creation'] = 'technicien';
            $data['createur_id']     = $user->technicien->id;
            $data['createur_type']   = Technicien::class;
            // client_id doit être fourni dans la requête pour un ticket créé par technicien
        } else {
            // Admin
            $data['source_creation'] = 'administrateur';
            $data['createur_id']     = $user->id;
            $data['createur_type']   = User::class;
        }

        $ticket = Ticket::create($data);

        // Notification à tous les admins pour un nouveau ticket
        if ($user->isClient()) {
            User::where('role', 'administrateur')->each(function ($admin) use ($ticket) {
                $admin->notify(new TicketCreatedNotification($ticket));
            });
        }

        return response()->json([
            'message' => 'Ticket créé avec succès.',
            'ticket'  => new TicketResource($ticket->load(['client.user', 'categorie'])),
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
            'ticket'  => new TicketResource($ticket->fresh()),
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
        Gate::authorize('update', $ticket);

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

    private function transitionner(
        Ticket $ticket,
        array $statutsAutorises,
        string $nouveauStatut,
        string $message,
        array $attributsSupplementaires = []
    ): JsonResponse {
        if (!in_array($ticket->statut, $statutsAutorises, true)) {
            throw ValidationException::withMessages([
                'statut' => "Transition impossible depuis le statut « {$ticket->statut} ».",
            ]);
        }

        $ancienStatut = $ticket->statut;
        $ticket->update([
            'statut' => $nouveauStatut,
            ...$attributsSupplementaires,
        ]);

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
