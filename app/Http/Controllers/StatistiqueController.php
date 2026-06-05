<?php

namespace App\Http\Controllers;

use App\Models\Statistique;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatistiqueController extends Controller
{
    /** GET /api/statistiques - liste les rapports générés */
    public function index(): JsonResponse
    {
        $stats = Statistique::with(['generePar', 'filtreClient.user', 'filtreCategorie'])
            ->orderByDesc('created_at')
            ->paginate(15);

        return response()->json($stats);
    }

    /** POST /api/statistiques/generer - calcule et sauvegarde un snapshot */
    public function generer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'periode_debut'      => 'nullable|date',
            'periode_fin'        => 'nullable|date|after_or_equal:periode_debut',
            'filtre_client_id'   => 'nullable|uuid|exists:clients,id',
            'filtre_categorie_id'=> 'nullable|uuid|exists:categories,id',
        ]);

        $stat = Statistique::calculer(
            $request->user()->id,
            isset($data['periode_debut'])       ? new \DateTime($data['periode_debut'])       : null,
            isset($data['periode_fin'])         ? new \DateTime($data['periode_fin'])         : null,
            $data['filtre_client_id']           ?? null,
            $data['filtre_categorie_id']        ?? null,
        );

        return response()->json($stat->load(['generePar', 'filtreClient.user', 'filtreCategorie']), 201);
    }

    /** GET /api/statistiques/{id} */
    public function show(Statistique $statistique): JsonResponse
    {
        return response()->json($statistique->load(['generePar', 'filtreClient.user', 'filtreCategorie']));
    }
}
