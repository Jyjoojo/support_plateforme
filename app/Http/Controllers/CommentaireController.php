<?php

namespace App\Http\Controllers;

use App\Http\Resources\CommentaireResource;
use App\Models\Commentaire;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketCommentedNotification;
use App\Notifications\TicketSolutionProposedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CommentaireController extends Controller
{
    /**
     * GET /api/tickets/{ticket}/commentaires
     */
    public function index(Ticket $ticket): JsonResponse
    {
        Gate::authorize('view', $ticket);

        $commentaires = $ticket->commentaires()
            ->with('auteur')
            ->orderBy('created_at')
            ->get();

        return response()->json(CommentaireResource::collection($commentaires));
    }

    /**
     * POST /api/tickets/{ticket}/commentaires
     */
    public function store(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('view', $ticket); // peut voir = peut commenter

        $data = $request->validate([
            'contenu' => 'required|string|min:2',
            'est_solution' => 'boolean',
        ]);

        if (($data['est_solution'] ?? false)
            && ! in_array($ticket->statut, ['en_cours', 'en_attente'], true)) {
            throw ValidationException::withMessages([
                'est_solution' => 'Seul un ticket en cours ou en attente peut être résolu.',
            ]);
        }

        if ($data['est_solution'] ?? false) {
            Gate::authorize('proposerSolution', $ticket);

            if ($ticket->commentaires()->where('est_solution', true)
                ->whereNull('solution_validee_at')->whereNull('solution_rejetee_at')->exists()) {
                throw ValidationException::withMessages([
                    'est_solution' => 'Une solution attend déjà la validation du client.',
                ]);
            }
        }

        $commentaire = DB::transaction(function () use ($request, $ticket, $data) {
            $commentaire = Commentaire::create([
                'ticket_id' => $ticket->id,
                'auteur_id' => $request->user()->id,
                'contenu' => $data['contenu'],
                'est_solution' => $data['est_solution'] ?? false,
            ]);

            if ($commentaire->est_solution && $ticket->statut === 'en_cours') {
                $ticket->mettreEnAttente();
            }

            return $commentaire;
        });

        if ($commentaire->est_solution && $ticket->client?->user) {
            $ticket->client->user->notify(new TicketSolutionProposedNotification($ticket, $commentaire));
        }

        // La proposition possède sa propre notification avec les actions de validation.
        if (! $commentaire->est_solution) {
            $this->notifierNouveauCommentaire($ticket, $commentaire);
        }

        return response()->json([
            'message' => 'Commentaire ajouté.',
            'commentaire' => new CommentaireResource($commentaire->load('auteur')),
        ], 201);
    }

    /**
     * PATCH /api/commentaires/{commentaire}  (shallow route)
     */
    public function update(Request $request, Ticket $ticket, Commentaire $commentaire): JsonResponse
    {
        abort_unless($commentaire->ticket_id === $ticket->id, 404);
        Gate::authorize('update', $commentaire);

        $commentaire->update($request->validate([
            'contenu' => 'required|string|min:2',
        ]));

        return response()->json([
            'message' => 'Commentaire modifié.',
            'commentaire' => new CommentaireResource($commentaire->fresh()->load('auteur')),
        ]);
    }

    /**
     * DELETE /api/commentaires/{commentaire}  (shallow route)
     */
    public function destroy(Request $request, Ticket $ticket, Commentaire $commentaire): JsonResponse
    {
        abort_unless($commentaire->ticket_id === $ticket->id, 404);
        Gate::authorize('delete', $commentaire);
        $commentaire->delete();

        return response()->json(['message' => 'Commentaire supprimé.']);
    }

    private function notifierNouveauCommentaire(Ticket $ticket, Commentaire $commentaire): void
    {
        $destinataires = [];
        $auteurId = $commentaire->auteur_id;

        // Notifier le client si le commentaire vient d'un technicien
        if ($ticket->client && $ticket->client->user_id !== $auteurId) {
            $destinataires[] = $ticket->client->user_id;
        }

        // Notifier le technicien assigné si le commentaire vient du client
        $assignation = $ticket->assignationActive;
        if ($assignation && $assignation->technicien->user_id !== $auteurId) {
            $destinataires[] = $assignation->technicien->user_id;
        }

        foreach ($destinataires as $userId) {
            $user = User::find($userId);
            if ($user) {
                $user->notify(new TicketCommentedNotification($ticket, $commentaire));
            }
        }
    }
}
