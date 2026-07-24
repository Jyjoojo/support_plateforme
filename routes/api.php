<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ArticleBaseController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\CommentaireController;
use App\Http\Controllers\PieceJointeController;
use App\Http\Controllers\AssignationController;
use App\Http\Controllers\CategorieController;
use App\Http\Controllers\StatistiqueController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RapportController;

/*
|--------------------------------------------------------------------------
| API Routes — FIRST INFO Ticketing
|--------------------------------------------------------------------------
| Préfixe /api appliqué automatiquement par bootstrap/app.php
| Toutes les routes sont stateless (Sanctum token auth)
*/

// Swagger documentation endpoint
Route::get('/docs', function () {
    $docsPath = storage_path('api-docs/api-docs.json');
    if (!file_exists($docsPath)) {
        return response()->json(['error' => 'Documentation not found'], 404);
    }
    return response()->file($docsPath, [
        'Content-Type' => 'application/json',
    ]);
});

// ═══════════════════════════════════════════════════════════
// ROUTES PUBLIQUES — sans authentification
// ═══════════════════════════════════════════════════════════
Route::prefix('auth')->group(function () {
    Route::post('login',          [AuthController::class, 'login']);
    Route::post('forgot-password',[AuthController::class, 'forgotPassword']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);
});

// Base de connaissances : articles publiés accessibles sans compte
Route::get('articles',           [ArticleBaseController::class, 'index']);
Route::get('articles/{article}', [ArticleBaseController::class, 'show']);

// ═══════════════════════════════════════════════════════════
// ROUTES PROTÉGÉES — auth:sanctum requis
// ═══════════════════════════════════════════════════════════
Route::middleware('auth:sanctum')->group(function () {

    // ── Auth ────────────────────────────────────────────────
    Route::post('auth/logout',  [AuthController::class, 'logout']);
    Route::get('auth/me',       [AuthController::class, 'me']);
    Route::patch('auth/profil', [AuthController::class, 'updateProfil']);

    // ── Notifications (propres à l'utilisateur connecté) ────
    Route::prefix('notifications')->group(function () {
        Route::get('/',                           [NotificationController::class, 'index']);
        Route::patch('{id}/lire',                 [NotificationController::class, 'marquerLue']);
        Route::post('tout-lire',                  [NotificationController::class, 'toutMarquerLu']);
        Route::delete('{id}',                     [NotificationController::class, 'destroy']);
    });

    // ── Tickets ─────────────────────────────────────────────
    // Les policies contrôlent qui voit / modifie quoi
    Route::get('tickets/non-assignes', [TicketController::class, 'nonAssignes'])
        ->middleware('role:technicien,administrateur');
    Route::apiResource('tickets', TicketController::class);

    // Sous-ressources d'un ticket
    Route::prefix('tickets/{ticket}')->group(function () {
        Route::apiResource('commentaires',   CommentaireController::class)->shallow();
        Route::apiResource('pieces-jointes', PieceJointeController::class)
             ->only(['index', 'store', 'destroy'])
             ->shallow();
        Route::post('assignation',           [AssignationController::class, 'assigner']);
        Route::post('fermer',                [TicketController::class, 'fermer']);
        Route::post('reouvrir',              [TicketController::class, 'reOuvrir']);
    });

    // ── Catégories ─────────────────────────────────────────
    Route::apiResource('categories', CategorieController::class)
         ->parameters(['categories' => 'categorie']);

    // ═══════════════════════════════════════════════════════
    // ROUTES TECHNICIEN — rôles : technicien + administrateur
    // ═══════════════════════════════════════════════════════
    Route::middleware('role:technicien,administrateur')->group(function () {
        // Auto-assignation d'un ticket par le technicien lui-même
        Route::post('tickets/{ticket}/auto-assigner', [AssignationController::class, 'autoAssigner'])
            ->middleware('role:technicien');

        // Gestion base de connaissances
        Route::apiResource('articles', ArticleBaseController::class)
             ->except(['index', 'show']); // index/show déjà définis en public
        Route::post('articles/{article}/publier',  [ArticleBaseController::class, 'publier']);
        Route::post('articles/{article}/archiver', [ArticleBaseController::class, 'archiver']);
    });

    // ═══════════════════════════════════════════════════════
    // ROUTES ADMINISTRATEUR uniquement
    // ═══════════════════════════════════════════════════════
    Route::middleware('role:administrateur')->group(function () {
        // Gestion des utilisateurs
        Route::apiResource('users', UserController::class);
        Route::patch('users/{user}/toggle-actif', [UserController::class, 'toggleActif']);

        // Statistiques
        Route::get('statistiques',           [StatistiqueController::class, 'index']);
        Route::post('statistiques/generer',  [StatistiqueController::class, 'generer']);
        Route::get('statistiques/{id}',      [StatistiqueController::class, 'show']);

        // Rapports et statistiques
        Route::prefix('rapports')->group(function () {
            Route::get('tickets', [RapportController::class, 'tickets']);
            Route::get('base-de-connaissances', [RapportController::class, 'baseDeConnaissances']);
            Route::get('clients', [RapportController::class, 'clients']);
            Route::get('techniciens', [RapportController::class, 'techniciens']);
        });

        // Assignation manuelle par l'admin
        Route::post('tickets/{ticket}/assigner-technicien', [AssignationController::class, 'assignerParAdmin']);

        // Historique complet des assignations d'un ticket
        Route::get('tickets/{ticket}/assignations', [AssignationController::class, 'historique']);
    });
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
