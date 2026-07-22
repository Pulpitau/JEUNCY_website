<?php

use App\Http\Controllers\CfaOrganizationController;
use Illuminate\Support\Facades\Route;

Route::prefix('cfa-organization')->middleware(['auth:api', 'role:CFA'])->group(function () {
    Route::get('/', [CfaOrganizationController::class, 'show']);
    Route::post('/', [CfaOrganizationController::class, 'store']);
    Route::patch('/', [CfaOrganizationController::class, 'update']);
});
