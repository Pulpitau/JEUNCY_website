<?php

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    // Limites par IP : evite le brute-force/credential-stuffing sur
    // login/register tout en restant tolerant pour un utilisateur legitime
    // qui se trompe de mot de passe plusieurs fois de suite (7 essais avant
    // un blocage de 2 minutes, qui se reinitialise ensuite - demande
    // explicite du produit). forgot/reset-password restent sur une limite
    // separee, moins sensible a l'erreur de saisie repetee.
    Route::middleware('throttle:7,2')->group(function () {
        Route::post('login', [AuthController::class, 'login']);
        Route::post('register', [AuthController::class, 'register']);
    });
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('reset-password', [AuthController::class, 'resetPassword']);
    });
    Route::post('refresh', [AuthController::class, 'refresh']);
    Route::get('google', [AuthController::class, 'googleRedirect']);
    Route::get('google/callback', [AuthController::class, 'googleCallback']);

    Route::middleware('auth:api')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});
