<?php

use App\Http\Controllers\ExternalInterestController;
use Illuminate\Support\Facades\Route;

// Gestes du candidat sur les offres partenaires (La bonne alternance) :
// « Je garde » ou « Passer ». Pas de match possible — la candidature se
// fait sur le site d'origine.
Route::prefix('external-interests')
    ->middleware(['auth:api', 'role:CANDIDATE', 'match.age'])
    ->group(function () {
        Route::delete('last', [ExternalInterestController::class, 'destroyLast']);
        Route::get('/', [ExternalInterestController::class, 'index']);
        Route::post('/', [ExternalInterestController::class, 'store']);
        Route::patch('{externalInterest}/done', [ExternalInterestController::class, 'done'])
            ->whereNumber('externalInterest');
    });
