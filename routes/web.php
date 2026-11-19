<?php

use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\NikniqController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ScoreController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

// The game is playable by anyone; it runs entirely in the browser.
Route::view('/play', 'game')->name('game');
Route::get('/leaderboard', [ScoreController::class, 'leaderboard'])->name('leaderboard');
Route::view('/how-to-play', 'how-to-play')->name('how-to-play');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');

    Route::middleware('throttle:10,1')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/register', [AuthController::class, 'register']);
    });

    Route::middleware('throttle:20,1')->group(function () {
        Route::get('/auth/google', [GoogleController::class, 'redirect'])->name('auth.google');
        Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('auth.google.callback');
        Route::get('/auth/nikniq', [NikniqController::class, 'redirect'])->name('auth.nikniq');
        // The portal registers games with /auth/callback as the redirect URI.
        Route::get('/auth/callback', [NikniqController::class, 'callback'])->name('auth.callback');
    });
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/scores', [ScoreController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('scores.store');
});
