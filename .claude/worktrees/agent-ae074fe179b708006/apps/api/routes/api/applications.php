<?php

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\JobOfferApplicationController;
use Illuminate\Support\Facades\Route;

Route::prefix('applications')->middleware('auth:api')->group(function () {
    Route::post('/', [ApplicationController::class, 'store'])->middleware('role:CANDIDATE');
    Route::get('/', [ApplicationController::class, 'index'])->middleware('role:CANDIDATE');
    Route::patch('{application}/status', [JobOfferApplicationController::class, 'updateStatus'])
        ->middleware('role:COMPANY,CFA');
});

Route::get('job-offers/{jobOffer}/applications', [JobOfferApplicationController::class, 'index'])
    ->middleware(['auth:api', 'role:COMPANY,CFA']);
