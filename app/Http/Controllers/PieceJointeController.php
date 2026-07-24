<?php

namespace App\Http\Controllers;

use App\Models\PieceJointe;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Gate;

class PieceJointeController extends Controller
{
    /** GET /api/tickets/{ticket}/pieces-jointes */
    public function index(Ticket $ticket): JsonResponse
    {
        Gate::authorize('view', $ticket);

        return response()->json($ticket->piecesJointes);
    }

    /** POST /api/tickets/{ticket}/pieces-jointes */
    public function store(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('view', $ticket);

        $request->validate([
            'fichier'   => 'required|file|max:10240|mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg,zip',
        ]);

        $fichier    = $request->file('fichier');
        $chemin     = $fichier->store("tickets/{$ticket->id}", 'private');

        $pieceJointe = PieceJointe::create([
            'ticket_id'     => $ticket->id,
            'nom_fichier'   => $fichier->getClientOriginalName(),
            'chemin_fichier'=> $chemin,
            'type_mime'     => $fichier->getMimeType(),
            'taille'        => $fichier->getSize(),
        ]);

        return response()->json($pieceJointe, 201);
    }

    /** DELETE /api/pieces-jointes/{pieceJointe} (shallow) */
    public function destroy(Request $request, PieceJointe $pieceJointe): JsonResponse
    {
        Gate::authorize('delete', $pieceJointe->ticket);

        Storage::disk('private')->delete($pieceJointe->chemin_fichier);
        $pieceJointe->delete();

        return response()->json(['message' => 'Pièce jointe supprimée.']);
    }
}
