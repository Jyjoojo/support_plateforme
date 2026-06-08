<?php

namespace App\Http\Controllers;

use App\Models\Assignation;
use App\Models\Technicien;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\AssignationResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use App\Notifications\TicketAssignedNotification;

class AssignationController extends Controller
{
    /**
     * POST /api/tickets/{ticket}/assignation
     * Assignation manuelle (admin ou technicien selon policy)
     */
    public function assigner(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('assigner', $ticket);

        $request->validate([
            'technicien_id' => 'required|uuid|exists:techniciens,id',
            'motif'         => 'nullable|string|max:500',
        ]);

        $assignation = Assignation::create([
            'ticket_id'       => $ticket->id,
            'technicien_id'   => $request->technicien_id,
            'assigne_par_id'  => $request->user()->id,
            'methode'         => 'manuelle',
            'motif'           => $request->motif,
            'date_assignation' => now(),
        ]);

        $ticket->update(['statut' => 'en_cours']);

        // Notifier le technicien
        $technicien = Technicien::find($request->technicien_id);
        if ($technicien && $technicien->user) {
            $technicien->user->notify(new TicketAssignedNotification($ticket, $assignation));
        }

        return response()->json([
            'message'     => 'Ticket assigné avec succès.',
            'assignation' => new AssignationResource($assignation->load('technicien.user')),
        ], 201);
    }

    /**
     * POST /api/tickets/{ticket}/auto-assigner
     * Le technicien s'auto-assigne
     */
    public function autoAssigner(Request $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();

        if (!$user->isTechnicien()) {
            return response()->json(['message' => 'Réservé aux techniciens.'], 403);
        }

        $assignation = Assignation::create([
            'ticket_id'        => $ticket->id,
            'technicien_id'    => $user->technicien->id,
            'assigne_par_id'   => $user->id,
            'methode'          => 'auto_assignation',
            'date_assignation' => now(),
        ]);

        $ticket->update(['statut' => 'en_cours']);

        return response()->json([
            'message'     => 'Vous avez pris en charge ce ticket.',
            'assignation' => new AssignationResource($assignation),
        ], 201);
    }

    /**
     * POST /api/tickets/{ticket}/assigner-technicien
     * Assignation par l'admin avec sélection technicien
     */
    public function assignerParAdmin(Request $request, Ticket $ticket): JsonResponse
    {
        return $this->assigner($request, $ticket);
    }

    /**
     * GET /api/tickets/{ticket}/assignations
     * Historique complet des assignations (admin)
     */
    public function historique(Ticket $ticket): JsonResponse
    {
        $assignations = $ticket->assignations()
            ->with(['technicien.user', 'assignePar'])
            ->orderByDesc('date_assignation')
            ->get();

        return response()->json(AssignationResource::collection($assignations));
    }
}
