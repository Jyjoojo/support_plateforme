<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\Notification;

use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use App\Http\Resources\TicketResource;
use Illuminate\Support\Facades\Gate;

class TicketController extends Controller
{
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
            $query->whereHas('assignations', fn($q) =>
                $q->where('technicien_id', $user->technicien->id)
            );
        }
        // Admin → tous les tickets

        // Filtres query string
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
            $data['createur_type']   = \App\Models\Client::class;
            $data['client_id']       = $user->client->id;
        } elseif ($user->isTechnicien()) {
            $data['source_creation'] = 'technicien';
            $data['createur_id']     = $user->technicien->id;
            $data['createur_type']   = \App\Models\Technicien::class;
            // client_id doit être fourni dans la requête pour un ticket créé par technicien
        } else {
            // Admin
            $data['source_creation'] = 'administrateur';
            $data['createur_id']     = $user->id;
            $data['createur_type']   = \App\Models\User::class;
        }

        $ticket = Ticket::create($data);

        // Notification à tous les admins pour un nouveau ticket
        if ($user->isClient()) {
            \App\Models\User::where('role', 'administrateur')->each(function ($admin) use ($ticket) {
                Notification::envoyer(
                    $admin->id,
                    "Nouveau ticket : {$ticket->titre}",
                    'nouveau_ticket',
                    $ticket->id
                );
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
            Notification::envoyer(
                $ticket->client->user_id,
                "Statut du ticket #{$ticket->id} changé : {$ancienStatut} → {$ticket->statut}",
                'statut_change',
                $ticket->id
            );
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

    /**
     * POST /api/tickets/{ticket}/fermer
     */
    public function fermer(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('update', $ticket);
        $ticket->fermer();

        return response()->json(['message' => 'Ticket fermé.', 'ticket' => new TicketResource($ticket)]);
    }

    /**
     * POST /api/tickets/{ticket}/reouvrir
     */
    public function reOuvrir(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('update', $ticket);
        $ticket->update(['statut' => 'en_cours']);

        return response()->json(['message' => 'Ticket réouvert.', 'ticket' => new TicketResource($ticket)]);
    }
}
