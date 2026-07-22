<?php

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    // Limites par IP : evite le brute-force/credential-stuffing sur login,
    // la creation de comptes en masse, et l'email-bombing via forgot-password.
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('login', [AuthController::class, 'login']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('reset-password', [AuthController::class, 'resetPassword']);
    });
    // register() revele forcement si un email existe deja (EMAIL_ALREADY_EXISTS,
    // 409) — un vrai utilisateur a besoin de ce message pour comprendre qu'il
    // doit se connecter plutot que reessayer. Impossible a masquer totalement
    // sans introduire une verification d'email prealable (absente de cette
    // architecture). A defaut, une limite volontairement plus stricte que les
    // autres routes auth rend un scan d'enumeration en masse impraticable,
    // sans gener une inscription legitime (jamais besoin de 5 essais/10 min).
    Route::middleware('throttle:5,10')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
    });
    Route::post('refresh', [AuthController::class, 'refresh']);
    Route::get('google', [AuthController::class, 'googleRedirect']);
    Route::get('google/callback', [AuthController::class, 'googleCallback']);

    Route::middleware('auth:api')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});
