<?php

namespace App\Http\Controllers;

use App\Models\Commentaire;
use App\Models\Notification;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\CommentaireResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CommentaireController extends Controller
{
    /**
     * GET /api/tickets/{ticket}/commentaires
     */
    public function index(Ticket $ticket): JsonResponse
    {
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
            'contenu'      => 'required|string|min:2',
            'est_solution' => 'boolean',
        ]);

        $commentaire = Commentaire::create([
            'ticket_id'    => $ticket->id,
            'auteur_id'    => $request->user()->id,
            'contenu'      => $data['contenu'],
            'est_solution' => $data['est_solution'] ?? false,
        ]);

        // Si marqué comme solution → résoudre le ticket
        if ($commentaire->est_solution) {
            $ticket->resoudre();
        }

        // Notifier les parties prenantes (client + technicien assigné)
        $this->notifierNouveauCommentaire($ticket, $request->user()->id);

        return response()->json([
            'message'      => 'Commentaire ajouté.',
            'commentaire'  => new CommentaireResource($commentaire->load('auteur')),
        ], 201);
    }

    /**
     * PATCH /api/commentaires/{commentaire}  (shallow route)
     */
    public function update(Request $request, Commentaire $commentaire): JsonResponse
    {
        Gate::authorize('update', $commentaire);

        $commentaire->update($request->validate([
            'contenu' => 'required|string|min:2',
        ]));

        return response()->json([
            'message'     => 'Commentaire modifié.',
            'commentaire' => new CommentaireResource($commentaire->fresh()),
        ]);
    }

    /**
     * DELETE /api/commentaires/{commentaire}  (shallow route)
     */
    public function destroy(Request $request, Commentaire $commentaire): JsonResponse
    {
        Gate::authorize('delete', $commentaire);
        $commentaire->delete();

        return response()->json(['message' => 'Commentaire supprimé.']);
    }

    private function notifierNouveauCommentaire(Ticket $ticket, string $auteurId): void
    {
        $destinataires = [];

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
            Notification::envoyer(
                $userId,
                "Nouveau commentaire sur le ticket : {$ticket->titre}",
                'nouveau_commentaire',
                $ticket->id
            );
        }
    }
}
