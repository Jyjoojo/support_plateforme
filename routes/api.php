<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ArticleBaseController;

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

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
