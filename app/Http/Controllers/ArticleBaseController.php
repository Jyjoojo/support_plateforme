<?php

namespace App\Http\Controllers;

use App\Http\Resources\ArticleBaseResource;
use App\Models\ArticleBase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ArticleBaseController extends Controller
{
    private const DEFAULT_PER_PAGE = 10;

    private const MIN_PER_PAGE = 6;

    private const MAX_PER_PAGE = 100;

    private const RELATIONS = [
        'auteur:id,nom,prenom,email,role',
        'technicien.user',
        'categorie',
        'soumisPar:id,nom,prenom,email,role',
        'validePar:id,nom,prenom,email,role',
        'archivePar:id,nom,prenom,email,role',
    ];

    /** Résolutions issues des tickets du client connecté, brouillons inclus. */
    public function resolutionsClient(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isClient() && $user->client, 403);

        $articles = ArticleBase::query()
            ->with([
                ...self::RELATIONS,
                'ticket:id,annee,numero,titre,statut,date_resolution,client_id',
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
            ->paginate($this->perPage($request));

        $articles->through(fn (ArticleBase $article) => (new ArticleBaseResource($article))->resolve($request));

        return response()->json($articles);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user('sanctum');

        if ($user && ($user->isTechnicien() || $user->isAdmin())) {
            $query = ArticleBase::query();

            if ($request->boolean('only_archived') || $request->boolean('with_archived')) {
                abort_unless($user->isAdmin(), 403, 'Les archives sont réservées aux administrateurs.');

                $request->boolean('only_archived') ? $query->onlyTrashed() : $query->withTrashed();
            }

            $query->with(self::RELATIONS)
                ->when($request->filled('search'), fn ($q) => $q->recherche($request->string('search')->toString()))
                ->when($request->filled('categorie_id'), fn ($q) => $q->where('categorie_id', $request->input('categorie_id')))
                ->when($request->filled('statut'), fn ($q) => $q->where('statut_editorial', $request->input('statut')))
                ->when($request->filled('publie'), fn ($q) => $q->where('publie', $request->boolean('publie')))
                ->orderByDesc('created_at');

            return response()->json($query->paginate($this->perPage($request)));
        }

        $query = ArticleBase::publies()
            ->with(['auteur:id,nom,prenom,role', 'technicien.user', 'categorie'])
            ->when($request->filled('search'), fn ($q) => $q->recherche($request->string('search')->toString()))
            ->when($request->filled('categorie_id'), fn ($q) => $q->where('categorie_id', $request->input('categorie_id')))
            ->orderByDesc('vues');

        return response()->json($query->paginate($this->perPage($request)));
    }

    public function show(Request $request, string $article): JsonResponse
    {
        $user = $request->user('sanctum');
        $query = $user?->isAdmin() ? ArticleBase::withTrashed() : ArticleBase::query();
        $articleBase = $query->with([
            ...self::RELATIONS,
            'ticket:id,annee,numero,titre,statut,date_resolution,client_id',
            'commentaireSolution:id,ticket_id,contenu,solution_validee_at',
        ])->findOrFail($article);

        if ($articleBase->statut_editorial !== ArticleBase::STATUT_PUBLIE || $articleBase->trashed()) {
            abort_unless($user, 404, 'Article non disponible.');
            Gate::forUser($user)->authorize('view', $articleBase);
        } else {
            $articleBase->incrementerVues();
        }

        return response()->json(new ArticleBaseResource($articleBase));
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', ArticleBase::class);
        $data = $request->validate($this->articleRules(true));
        $user = $request->user();

        if (isset($data['mots_cles']) && is_array($data['mots_cles'])) {
            $data['mots_cles'] = implode(',', array_map('trim', $data['mots_cles']));
        }

        $article = ArticleBase::create([
            ...$data,
            'auteur_id' => $user->id,
            'technicien_id' => $user->technicien?->id,
            'statut_editorial' => ArticleBase::STATUT_BROUILLON,
            'publie' => false,
        ]);

        return response()->json([
            'message' => 'Article créé comme brouillon.',
            'article' => new ArticleBaseResource($article->load(self::RELATIONS)),
        ], 201);
    }

    public function update(Request $request, ArticleBase $article): JsonResponse
    {
        Gate::authorize('update', $article);
        $article->update($request->validate($this->articleRules(false)));

        return response()->json([
            'message' => 'Article mis à jour.',
            'article' => new ArticleBaseResource($article->fresh()->load(self::RELATIONS)),
        ]);
    }

    public function destroy(Request $request, ArticleBase $article): JsonResponse
    {
        return $this->archiver($request, $article);
    }

    public function soumettre(Request $request, ArticleBase $article): JsonResponse
    {
        Gate::authorize('submit', $article);
        $this->exigerStatut($article, [ArticleBase::STATUT_BROUILLON, ArticleBase::STATUT_A_CORRIGER]);
        $article->soumettre($request->user());

        return $this->workflowResponse('Article soumis à la validation administrative.', $article);
    }

    public function valider(Request $request, ArticleBase $article): JsonResponse
    {
        Gate::authorize('review', $article);
        $this->exigerStatut($article, [ArticleBase::STATUT_EN_ATTENTE]);
        $article->publier($request->user());

        return $this->workflowResponse('Article validé et publié.', $article);
    }

    public function refuser(Request $request, ArticleBase $article): JsonResponse
    {
        Gate::authorize('review', $article);
        $this->exigerStatut($article, [ArticleBase::STATUT_EN_ATTENTE]);
        $data = $request->validate(['motif' => ['required', 'string', 'min:5', 'max:2000']]);
        $article->refuser($request->user(), $data['motif']);

        return $this->workflowResponse('Article renvoyé à son auteur pour correction.', $article);
    }

    /** Publication directe d'un brouillon rédigé par un administrateur. */
    public function publier(Request $request, ArticleBase $article): JsonResponse
    {
        Gate::authorize('review', $article);
        $this->exigerStatut($article, [
            ArticleBase::STATUT_BROUILLON,
            ArticleBase::STATUT_A_CORRIGER,
            ArticleBase::STATUT_EN_ATTENTE,
        ]);
        $article->publier($request->user());

        return $this->workflowResponse('Article publié.', $article);
    }

    public function depublier(Request $request, ArticleBase $article): JsonResponse
    {
        Gate::authorize('review', $article);
        $this->exigerStatut($article, [ArticleBase::STATUT_PUBLIE]);
        $article->depublier();

        return $this->workflowResponse('Article retiré de la publication et replacé en brouillon.', $article);
    }

    public function archiver(Request $request, ArticleBase $article): JsonResponse
    {
        Gate::authorize('archive', $article);
        $article->archiver($request->user());

        return $this->workflowResponse('Article archivé.', $article);
    }

    public function restaurer(Request $request, string $article): JsonResponse
    {
        $articleBase = ArticleBase::onlyTrashed()->findOrFail($article);
        Gate::authorize('restore', $articleBase);
        $articleBase->restaurer();

        return $this->workflowResponse('Article restauré comme brouillon.', $articleBase);
    }

    private function articleRules(bool $creation): array
    {
        return [
            'titre' => [$creation ? 'required' : 'sometimes', 'string', 'max:255'],
            'contenu' => [$creation ? 'required' : 'sometimes', 'string'],
            'mots_cles' => ['nullable', 'string'],
            'categorie_id' => ['nullable', 'uuid', Rule::exists('categories', 'id')],
        ];
    }

    private function exigerStatut(ArticleBase $article, array $statuts): void
    {
        if (! in_array($article->statut_editorial, $statuts, true)) {
            throw ValidationException::withMessages([
                'statut_editorial' => "Action impossible depuis le statut « {$article->statut_editorial} ».",
            ]);
        }
    }

    private function workflowResponse(string $message, ArticleBase $article): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'article' => new ArticleBaseResource($article->load(self::RELATIONS)),
        ]);
    }

    private function perPage(Request $request): int
    {
        $requestedPerPage = filter_var($request->input('perPage'), FILTER_VALIDATE_INT);

        if ($requestedPerPage === false) {
            return self::DEFAULT_PER_PAGE;
        }

        return min(max($requestedPerPage, self::MIN_PER_PAGE), self::MAX_PER_PAGE);
    }
}
