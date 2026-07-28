<?php

use App\Http\Controllers\DeployController;
use Illuminate\Support\Facades\Route;

// API pure (voir apps/web pour le frontend React) : pas de vue Blade,
// juste un point de sonde minimal pour vérifier que le serveur répond.
Route::get('/', fn () => response()->json(['app' => 'Jeuncy API', 'status' => 'ok']));

// Deploiement sans SSH (voir DeployController) : routes inertes tant que
// DEPLOY_TOKEN n'est pas defini dans .env.
Route::get('/deploy/{token}/status', [DeployController::class, 'status']);
Route::get('/deploy/{token}/migrate', [DeployController::class, 'migrate']);
Route::get('/deploy/{token}/clear-cache', [DeployController::class, 'clearCache']);
