<?php

namespace App\Http\Controllers;

use App\Models\Assignation;
use App\Models\Technicien;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\AssignationResource;
use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
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

        $data = $request->validate([
            'technicien_id' => 'required|uuid|exists:techniciens,id',
            'motif'         => 'nullable|string|max:500',
        ]);

        $assignation = DB::transaction(function () use ($request, $ticket, $data) {
            $ticketVerrouille = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            Gate::authorize('assigner', $ticketVerrouille);
            $assignationActive = $ticketVerrouille->assignationActive()->first();

            if ($request->user()->isTechnicien()) {
                if (in_array($ticketVerrouille->statut, ['resolu', 'ferme'], true)) {
                    throw ValidationException::withMessages([
                        'ticket' => 'Un ticket résolu ou fermé ne peut pas être transféré.',
                    ]);
                }

                if ($assignationActive?->technicien_id === $data['technicien_id']) {
                    throw ValidationException::withMessages([
                        'technicien_id' => 'Sélectionnez un autre technicien pour transférer le ticket.',
                    ]);
                }
            }

            $nouvelleAssignation = Assignation::create([
                'ticket_id'        => $ticketVerrouille->id,
                'technicien_id'    => $data['technicien_id'],
                'assigne_par_id'   => $request->user()->id,
                'methode'          => 'manuelle',
                'motif'            => $data['motif'] ?? null,
                'date_assignation' => now(),
            ]);

            $ticketVerrouille->update(['statut' => 'en_cours']);

            return $nouvelleAssignation;
        });

        // Notifier le technicien
        $technicien = Technicien::find($data['technicien_id']);
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

        $assignation = DB::transaction(function () use ($user, $ticket) {
            $ticketVerrouille = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);

            if ($ticketVerrouille->assignations()->exists()) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Ce ticket est déjà assigné.',
                ], 409));
            }

            if (in_array($ticketVerrouille->statut, ['resolu', 'ferme'], true)) {
                throw ValidationException::withMessages([
                    'ticket' => 'Un ticket résolu ou fermé ne peut pas être pris en charge.',
                ]);
            }

            $nouvelleAssignation = Assignation::create([
                'ticket_id'        => $ticketVerrouille->id,
                'technicien_id'    => $user->technicien->id,
                'assigne_par_id'   => $user->id,
                'methode'          => 'auto_assignation',
                'date_assignation' => now(),
            ]);

            $ticketVerrouille->update(['statut' => 'en_cours']);

            return $nouvelleAssignation;
        });

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
