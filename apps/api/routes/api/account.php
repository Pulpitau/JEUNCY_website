<?php

use App\Http\Controllers\AccountController;
use Illuminate\Support\Facades\Route;

// Ouvert aux trois roles auto-gerables (CANDIDATE, COMPANY, CFA) et a ADMIN
// (export seulement : la suppression admin est bloquee cote service).
Route::prefix('account')->middleware('auth:api')->group(function () {
    Route::get('export', [AccountController::class, 'export']);
    Route::delete('/', [AccountController::class, 'destroy']);
});
