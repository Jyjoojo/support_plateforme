<?php

namespace App\Http\Controllers;

use App\Models\Categorie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CategorieController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Categorie::orderBy('libelle')->get());
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Categorie::class);
        $categorie = Categorie::create($request->validate([
            'libelle'     => 'required|string|unique:categories',
            'description' => 'nullable|string',
        ]));
        return response()->json($categorie, 201);
    }

    public function show(Categorie $categorie): JsonResponse
    {
        return response()->json($categorie);
    }

    public function update(Request $request, Categorie $categorie): JsonResponse
    {
        Gate::authorize('update', $categorie);
        $categorie->update($request->validate([
            'libelle'     => 'sometimes|string|unique:categories,libelle,' . $categorie->id,
            'description' => 'nullable|string',
        ]));
        return response()->json($categorie);
    }

    public function destroy(Categorie $categorie): JsonResponse
    {
        Gate::authorize('delete', $categorie);
        $categorie->delete();
        return response()->json(['message' => 'Catégorie supprimée.']);
    }
}
