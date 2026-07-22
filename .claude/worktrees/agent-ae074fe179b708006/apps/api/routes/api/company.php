<?php

use App\Http\Controllers\CompanyController;
use Illuminate\Support\Facades\Route;

Route::prefix('company')->middleware(['auth:api', 'role:COMPANY'])->group(function () {
    Route::get('/', [CompanyController::class, 'show']);
    Route::post('/', [CompanyController::class, 'store']);
    Route::patch('/', [CompanyController::class, 'update']);
});
