<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ScoreController;
use Illuminate\Support\Facades\Route;

// The game is playable by anyone; it runs entirely in the browser.
Route::view('/', 'game')->name('game');
Route::get('/leaderboard', [ScoreController::class, 'leaderboard'])->name('leaderboard');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');

    Route::middleware('throttle:10,1')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/register', [AuthController::class, 'register']);
    });
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/scores', [ScoreController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('scores.store');
});
