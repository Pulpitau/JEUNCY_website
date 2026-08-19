<?php

use App\Http\Controllers\Admin\JobOfferController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\StatsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VideoRoomController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware(['auth:api', 'role:ADMIN'])->group(function () {
    Route::get('stats', [StatsController::class, 'index']);

    Route::get('users', [UserController::class, 'index']);
    Route::post('users/{user}/suspend', [UserController::class, 'suspend']);
    Route::post('users/{user}/reactivate', [UserController::class, 'reactivate']);

    Route::get('job-offers', [JobOfferController::class, 'index']);
    // Apercu du rendu public d'une offre quel que soit son statut (brouillon
    // compris) — voir AdminService::previewJobOffer pour pourquoi ce chemin
    // est distinct de l'endpoint public.
    Route::get('job-offers/{jobOffer}/preview', [JobOfferController::class, 'preview']);
    Route::post('job-offers/{jobOffer}/archive', [JobOfferController::class, 'archive']);

    Route::get('payments', [PaymentController::class, 'index']);
    // Mouvement d'argent reel et irreversible cote Stripe : la seule route
    // admin qui agisse sur le compte bancaire. Garde-fous dans
    // PaymentService::refund.
    Route::post('payments/{payment}/refund', [PaymentController::class, 'refund']);

    Route::get('video-rooms', [VideoRoomController::class, 'index']);
    Route::post('video-rooms/{videoRoom}/end', [VideoRoomController::class, 'end']);
});
