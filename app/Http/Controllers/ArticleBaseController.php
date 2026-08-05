<?php

namespace App\Http\Controllers;

use App\Models\ArticleBase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Articles",
 *     description="Gestion de la base de connaissances (articles)"
 * )
 */
class ArticleBaseController extends Controller
{
    /** Résolutions issues des tickets du client connecté, brouillons inclus. */
    public function resolutionsClient(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isClient() && $user->client, 403);

        $articles = ArticleBase::query()
            ->with([
                'categorie',
                'technicien.user',
                'ticket:id,titre,statut,date_resolution,client_id',
                'commentaireSolution:id,ticket_id,contenu,solution_validee_at',
            ])
            ->whereHas('ticket', fn ($query) => $query
                ->where('client_id', $user->client->id)
                ->whereIn('statut', ['resolu', 'ferme']))
            ->whereHas('commentaireSolution', fn ($query) => $query
                ->where('est_solution', true)
                ->whereNotNull('solution_validee_at'))
            ->when($request->filled('search'), fn ($query) => $query->recherche($request->string('search')->toString()))
            ->when($request->filled('categorie_id'), fn ($query) => $query->where('categorie_id', $request->input('categorie_id')))
            ->orderByDesc('created_at')
            ->paginate(15);

        return response()->json($articles);
    }

    /**
     * @OA\Get(
     *     path="/api/articles",
     *     summary="Lister les articles publiés (public)",
     *     tags={"Articles"},
     *
     *     @OA\Parameter(name="search", in="query", description="Rechercher par titre ou contenu", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="categorie_id", in="query", description="Filtrer par catégorie", required=false, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Liste paginée des articles"
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Vue Technicien/Admin : accès à tous les articles avec filtres
        if ($user && ($user->isTechnicien() || $user->isAdmin())) {
            $query = ArticleBase::query();

            if ($request->boolean('only_archived')) {
                $query->onlyTrashed();
            } elseif ($request->boolean('with_archived')) {
                $query->withTrashed();
            }

            $query->with(['technicien.user', 'categorie'])
                ->when($request->filled('search'), fn ($q) => $q->recherche($request->search))
                ->when($request->filled('categorie_id'), fn ($q) => $q->where('categorie_id', $request->categorie_id))
                ->when($request->filled('publie'), fn ($q) => $q->where('publie', $request->boolean('publie')))
                ->orderByDesc('created_at');

            return response()->json($query->paginate(20));
        }

        // Vue publique : uniquement les articles publiés et non archivés
        $query = ArticleBase::publies()
            ->with(['technicien.user', 'categorie'])
            ->when($request->search, fn ($q) => $q->recherche($request->search))
            ->when($request->categorie_id, fn ($q) => $q->where('categorie_id', $request->categorie_id))
            ->orderByDesc('vues');

        return response()->json($query->paginate(15));
    }

    /**
     * @OA\Get(
     *     path="/api/articles/{article}",
     *     summary="Afficher un article spécifique (public)",
     *     tags={"Articles"},
     *
     *     @OA\Parameter(name="article", in="path", description="ID de l'article", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(response=200, description="Détails de l'article"),
     *     @OA\Response(response=404, description="Article non disponible")
     * )
     */
    public function show(ArticleBase $article): JsonResponse
    {
        if (! $article->publie) {
            return response()->json(['message' => 'Article non disponible.'], 404);
        }

        $article->incrementerVues();

        return response()->json($article->load(['technicien.user', 'categorie']));
    }

    /**
     * @OA\Post(
     *     path="/api/articles",
     *     summary="Créer un nouvel article",
     *     tags={"Articles"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"titre","contenu"},
     *
     *             @OA\Property(property="titre", type="string", maxLength=255, example="Titre de l'article"),
     *             @OA\Property(property="contenu", type="string", example="Contenu détaillé..."),
     *             @OA\Property(property="mots_cles", type="string", nullable=true, example="support, aide"),
     *             @OA\Property(property="categorie_id", type="string", format="uuid", nullable=true)
     *         )
     *     ),
     *
     *     @OA\Response(response=201, description="Article créé avec succès"),
     *     @OA\Response(response=422, description="Erreur de validation des données")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'titre' => 'required|string|max:255',
            'contenu' => 'required|string',
            'mots_cles' => 'nullable|string',
            'categorie_id' => 'nullable|uuid|exists:categories,id',
        ]);

        $article = ArticleBase::create([
            ...$data,
            'technicien_id' => $request->user()->technicien?->id,
            'publie' => false,
        ]);

        return response()->json($article, 201);
    }

    /**
     * @OA\Put(
     *     path="/api/articles/{article}",
     *     summary="Mettre à jour un article",
     *     tags={"Articles"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="article", in="path", description="ID de l'article", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="titre", type="string", maxLength=255),
     *             @OA\Property(property="contenu", type="string"),
     *             @OA\Property(property="mots_cles", type="string", nullable=true),
     *             @OA\Property(property="categorie_id", type="string", format="uuid", nullable=true)
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Article mis à jour avec succès"),
     *     @OA\Response(response=422, description="Erreur de validation des données"),
     *     @OA\Response(response=404, description="Article non trouvé")
     * )
     */
    public function update(Request $request, ArticleBase $article): JsonResponse
    {
        $article->update($request->validate([
            'titre' => 'sometimes|string|max:255',
            'contenu' => 'sometimes|string',
            'mots_cles' => 'nullable|string',
            'categorie_id' => 'nullable|uuid|exists:categories,id',
        ]));

        return response()->json($article);
    }

    /**
     * @OA\Delete(
     *     path="/api/articles/{article}",
     *     summary="Supprimer (archiver) un article",
     *     tags={"Articles"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="article", in="path", description="ID de l'article", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(response=200, description="Article archivé"),
     *     @OA\Response(response=404, description="Article non trouvé")
     * )
     */
    public function destroy(ArticleBase $article): JsonResponse
    {
        $article->archiver();

        return response()->json(['message' => 'Article archivé.']);
    }

    /**
     * @OA\Post(
     *     path="/api/articles/{article}/publier",
     *     summary="Publier un article",
     *     tags={"Articles"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="article", in="path", description="ID de l'article", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(response=200, description="Article publié")
     * )
     */
    public function publier(ArticleBase $article): JsonResponse
    {
        $article->publier();

        return response()->json(['message' => 'Article publié.']);
    }

    /**
     * @OA\Post(
     *     path="/api/articles/{article}/archiver",
     *     summary="Archiver un article manuellement",
     *     tags={"Articles"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="article", in="path", description="ID de l'article", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(response=200, description="Article archivé")
     * )
     */
    public function archiver(ArticleBase $article): JsonResponse
    {
        $article->archiver();

        return response()->json(['message' => 'Article archivé.']);
    }
}
