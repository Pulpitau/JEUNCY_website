<?php

use App\Http\Controllers\JobOfferController;
use App\Http\Controllers\PublicJobOfferController;
use Illuminate\Support\Facades\Route;

// Public : recherche/consultation des offres publiees, aucune authentification.
// Enregistre avant le groupe authentifie ci-dessous pour eviter que le segment
// "search" soit intercepte par le route model binding de {jobOffer}.
Route::get('job-offers/search', [PublicJobOfferController::class, 'index']);
Route::get('job-offers/{jobOffer}', [PublicJobOfferController::class, 'show'])->whereNumber('jobOffer');

Route::prefix('job-offers')->middleware(['auth:api', 'role:COMPANY,CFA'])->group(function () {
    Route::get('/', [JobOfferController::class, 'index']);
    Route::post('/', [JobOfferController::class, 'store']);
    Route::patch('{jobOffer}', [JobOfferController::class, 'update']);
    Route::post('{jobOffer}/archive', [JobOfferController::class, 'archive']);
});
