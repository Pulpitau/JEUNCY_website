<?php

use App\Http\Controllers\CompanyController;
use App\Http\Controllers\PublicCompanyController;
use Illuminate\Support\Facades\Route;

// Annuaire public des entreprises inscrites, aucune authentification requise.
Route::get('companies', [PublicCompanyController::class, 'index']);
Route::get('companies/{company}', [PublicCompanyController::class, 'show'])->whereNumber('company');

Route::prefix('company')->middleware(['auth:api', 'role:COMPANY'])->group(function () {
    Route::get('/', [CompanyController::class, 'show']);
    Route::post('/', [CompanyController::class, 'store']);
    Route::patch('/', [CompanyController::class, 'update']);
    Route::post('logo', [CompanyController::class, 'uploadLogo']);
    Route::delete('logo', [CompanyController::class, 'removeLogo']);
});
