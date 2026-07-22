<?php

use Illuminate\Support\Facades\Route;

// API pure (voir apps/web pour le frontend React) : pas de vue Blade,
// juste un point de sonde minimal pour vérifier que le serveur répond.
Route::get('/', fn () => response()->json(['app' => 'Jeuncy API', 'status' => 'ok']));
