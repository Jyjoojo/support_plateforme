<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ArticleBase;
use Illuminate\Http\JsonResponse;

class ArticleBaseController extends Controller
{
    /** GET /api/articles (public) */
    public function index(Request $request): JsonResponse
    {
        $articles = ArticleBase::publies()
            ->with(['technicien.user', 'categorie'])
            ->when($request->search, fn($q) => $q->recherche($request->search))
            ->when($request->categorie_id, fn($q) => $q->where('categorie_id', $request->categorie_id))
            ->orderByDesc('vues')
            ->paginate(15);

        return response()->json($articles);
    }

    /** GET /api/articles/{article} (public) */
    public function show(ArticleBase $article): JsonResponse
    {
        if (!$article->publie) {
            return response()->json(['message' => 'Article non disponible.'], 404);
        }

        $article->incrementerVues();

        return response()->json($article->load(['technicien.user', 'categorie']));
    }

    /** POST /api/articles */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'titre'        => 'required|string|max:255',
            'contenu'      => 'required|string',
            'mots_cles'    => 'nullable|string',
            'categorie_id' => 'nullable|uuid|exists:categories,id',
        ]);

        $article = ArticleBase::create([
            ...$data,
            'technicien_id' => $request->user()->technicien?->id,
            'publie'        => false,
        ]);

        return response()->json($article, 201);
    }

    /** PUT /api/articles/{article} */
    public function update(Request $request, ArticleBase $article): JsonResponse
    {
        $article->update($request->validate([
            'titre'        => 'sometimes|string|max:255',
            'contenu'      => 'sometimes|string',
            'mots_cles'    => 'nullable|string',
            'categorie_id' => 'nullable|uuid|exists:categories,id',
        ]));

        return response()->json($article);
    }

    /** DELETE /api/articles/{article} */
    public function destroy(ArticleBase $article): JsonResponse
    {
        $article->archiver();
        return response()->json(['message' => 'Article archivé.']);
    }

    /** POST /api/articles/{article}/publier */
    public function publier(ArticleBase $article): JsonResponse
    {
        $article->publier();
        return response()->json(['message' => 'Article publié.']);
    }

    /** POST /api/articles/{article}/archiver */
    public function archiver(ArticleBase $article): JsonResponse
    {
        $article->archiver();
        return response()->json(['message' => 'Article archivé.']);
    }
}
