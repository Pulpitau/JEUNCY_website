<?php

use App\Http\Controllers\DiscoverController;
use Illuminate\Support\Facades\Route;

// Les deux piles de Decouvrir (MOBILE.md §3 et §4).
//
// Cote candidat, match.age en plus du role : seize ans minimum sur toute
// route du match, quel que soit le client. Cote employeur, les gardes
// (entreprise verifiee, perimetre departemental) vivent dans le service,
// parce qu'elles dependent de l'offre visee et pas seulement de l'appelant.
Route::middleware('auth:api')->group(function () {
    Route::get('discover/offers', [DiscoverController::class, 'offers'])
        ->middleware(['role:CANDIDATE', 'match.age']);

    Route::get('discover/candidates', [DiscoverController::class, 'candidates'])
        ->middleware('role:COMPANY,CFA');
});
