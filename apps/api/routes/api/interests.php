<?php

use App\Http\Controllers\InterestController;
use Illuminate\Support\Facades\Route;

// « Ca m'interesse » / « Passer » sur les offres Jeuncy, des deux cotes.
//
// interests/batch et interests/last sont declarees AVANT toute route a
// parametre pour que ces segments ne soient jamais pris pour un
// identifiant (meme precaution que job-offers/search).
Route::prefix('interests')
    ->middleware(['auth:api', 'role:CANDIDATE,COMPANY,CFA', 'match.age'])
    ->group(function () {
        Route::post('batch', [InterestController::class, 'batch']);
        Route::delete('last', [InterestController::class, 'destroyLast']);
        Route::post('/', [InterestController::class, 'store']);
    });
