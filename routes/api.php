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

/*
|--------------------------------------------------------------------------
| API Routes — FIRST INFO Ticketing
|--------------------------------------------------------------------------
| Préfixe /api appliqué automatiquement par bootstrap/app.php
| Toutes les routes sont stateless (Sanctum token auth)
*/

// ═══════════════════════════════════════════════════════════
// ROUTES PUBLIQUES — sans authentification
// ═══════════════════════════════════════════════════════════
Route::prefix('auth')->group(function () {
    Route::post('login',          [AuthController::class, 'login']);
    Route::post('forgot-password',[AuthController::class, 'forgotPassword']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);
});

// Base de connaissances : articles publiés accessibles sans compte
Route::get('articles',      [ArticleBaseController::class, 'index']);
Route::get('articles/{id}', [ArticleBaseController::class, 'show']);

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
    Route::apiResource('categories', CategorieController::class);

    // ═══════════════════════════════════════════════════════
    // ROUTES TECHNICIEN — rôles : technicien + administrateur
    // ═══════════════════════════════════════════════════════
    Route::middleware('role:technicien,administrateur')->group(function () {
        // Auto-assignation d'un ticket par le technicien lui-même
        Route::post('tickets/{ticket}/auto-assigner', [AssignationController::class, 'autoAssigner']);

        // Gestion base de connaissances
        Route::apiResource('articles', ArticleBaseController::class)
             ->except(['index', 'show']); // index/show déjà définis en public
        Route::post('articles/{article}/publier',  [ArticleBaseController::class, 'publier']);
        Route::post('articles/{article}/archiver', [ArticleBaseController::class, 'archiver']);
    });
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
