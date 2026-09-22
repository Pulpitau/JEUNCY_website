<?php

use App\Http\Controllers\BlockController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

// Blocages et signalements (MOBILE.md §7). Ouverts a tout compte connecte :
// un candidat bloque un recruteur, un recruteur bloque un candidat, et
// personne ne doit avoir a prouver un role pour se proteger.
//
// Pas de match.age ici, a dessein : quelqu'un de trop jeune pour Decouvrir
// doit quand meme pouvoir signaler ce qu'il a vu ailleurs sur le site.
Route::middleware('auth:api')->group(function () {
    Route::post('blocks', [BlockController::class, 'store']);
    Route::delete('blocks/{userBlock}', [BlockController::class, 'destroy'])->whereNumber('userBlock');
    Route::post('reports', [ReportController::class, 'store']);
});
