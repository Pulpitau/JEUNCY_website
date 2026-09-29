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
        // Retirer une offre gardee. Declaree APRES `delete('last')`, qui est
        // litterale : l'ordre importe, une route parametree posee avant
        // capturerait « last » comme un identifiant.
        Route::delete('{externalInterest}', [ExternalInterestController::class, 'destroy'])
            ->whereNumber('externalInterest');
    });
