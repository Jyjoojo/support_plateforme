<?php

namespace App\Http\Controllers;

use App\Http\Resources\PieceJointeResource;
use App\Models\PieceJointe;
use App\Models\Ticket;
use App\Services\PieceJointeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PieceJointeController extends Controller
{
    /** GET /api/tickets/{ticket}/pieces-jointes */
    public function index(Ticket $ticket): JsonResponse
    {
        Gate::authorize('view', $ticket);

        return response()->json(PieceJointeResource::collection($ticket->piecesJointes));
    }

    /** POST /api/tickets/{ticket}/pieces-jointes */
    public function store(Request $request, Ticket $ticket, PieceJointeService $service): JsonResponse
    {
        Gate::authorize('ajouterPieceJointe', $ticket);

        $request->validate([
            'fichier' => 'required|file|max:10240|mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg',
        ]);

        $pieceJointe = $service->enregistrer($ticket, $request->file('fichier'), $request->user());

        return response()->json(new PieceJointeResource($pieceJointe), 201);
    }

    public function afficher(PieceJointe $pieceJointe): StreamedResponse
    {
        Gate::authorize('view', $pieceJointe);

        return $this->servirFichier($pieceJointe, 'inline');
    }

    public function telecharger(PieceJointe $pieceJointe): StreamedResponse
    {
        Gate::authorize('view', $pieceJointe);

        return $this->servirFichier($pieceJointe, 'attachment');
    }

    /** DELETE /api/pieces-jointes/{pieceJointe} (shallow) */
    public function destroy(PieceJointe $pieceJointe): JsonResponse
    {
        Gate::authorize('delete', $pieceJointe);

        Storage::disk(config('filesystems.attachments_disk'))->delete($pieceJointe->chemin_fichier);
        $pieceJointe->delete();

        return response()->json(['message' => 'Pièce jointe supprimée.']);
    }

    private function servirFichier(PieceJointe $pieceJointe, string $disposition): StreamedResponse
    {
        $disque = Storage::disk(config('filesystems.attachments_disk', 'private'));

        abort_unless($disque->exists($pieceJointe->chemin_fichier), 404, 'Fichier introuvable.');

        $flux = $disque->readStream($pieceJointe->chemin_fichier);
        abort_if($flux === false, 500, 'Impossible de lire le fichier.');

        return response()->stream(function () use ($flux): void {
            fpassthru($flux);
            fclose($flux);
        }, 200, [
            'Content-Type' => $pieceJointe->type_mime,
            'Content-Length' => (string) $pieceJointe->taille,
            'Content-Disposition' => HeaderUtils::makeDisposition(
                $disposition,
                $pieceJointe->nom_fichier,
                'piece-jointe'
            ),
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
