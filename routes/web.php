<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name' => config('app.name'),
        'status' => 'ok',
        'documentation' => '/swagger',
    ]);
})->name('home');

// Swagger API Documentation
Route::get('/swagger', function () {
    return view('swagger');
})->name('swagger');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
