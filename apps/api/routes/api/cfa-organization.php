<?php

use App\Http\Controllers\CfaOrganizationController;
use App\Http\Controllers\PublicCfaOrganizationController;
use Illuminate\Support\Facades\Route;

// Annuaire public des CFA inscrits, aucune authentification requise.
Route::get('cfa-organizations', [PublicCfaOrganizationController::class, 'index']);
Route::get('cfa-organizations/{cfaOrganization}', [PublicCfaOrganizationController::class, 'show'])->whereNumber('cfaOrganization');

Route::prefix('cfa-organization')->middleware(['auth:api', 'role:CFA'])->group(function () {
    Route::get('/', [CfaOrganizationController::class, 'show']);
    Route::post('/', [CfaOrganizationController::class, 'store']);
    Route::patch('/', [CfaOrganizationController::class, 'update']);
    Route::post('logo', [CfaOrganizationController::class, 'uploadLogo']);
    Route::delete('logo', [CfaOrganizationController::class, 'removeLogo']);
});
