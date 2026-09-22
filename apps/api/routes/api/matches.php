<?php

use App\Http\Controllers\MatchController;
use Illuminate\Support\Facades\Route;

// Les matchs des deux roles. Une ligne qui n'appartient pas a l'appelant
// repond 404 et non 403 : dire « interdit » confirmerait son existence.
Route::prefix('matches')
    ->middleware(['auth:api', 'role:CANDIDATE,COMPANY,CFA', 'match.age'])
    ->group(function () {
        Route::get('/', [MatchController::class, 'index']);
        Route::get('{offerInterest}', [MatchController::class, 'show'])->whereNumber('offerInterest');
    });
