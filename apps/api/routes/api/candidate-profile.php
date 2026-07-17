<?php

use App\Http\Controllers\CandidateProfileController;
use App\Http\Controllers\CandidateSkillController;
use App\Http\Controllers\EducationController;
use App\Http\Controllers\ExperienceController;
use App\Http\Controllers\GeneratedCvController;
use Illuminate\Support\Facades\Route;

Route::prefix('candidate-profile')->middleware(['auth:api', 'role:CANDIDATE'])->group(function () {
    Route::get('/', [CandidateProfileController::class, 'show']);
    Route::post('/', [CandidateProfileController::class, 'store']);
    Route::patch('/', [CandidateProfileController::class, 'update']);

    Route::post('experiences', [ExperienceController::class, 'store']);
    Route::patch('experiences/{experience}', [ExperienceController::class, 'update']);
    Route::delete('experiences/{experience}', [ExperienceController::class, 'destroy']);

    Route::post('educations', [EducationController::class, 'store']);
    Route::patch('educations/{education}', [EducationController::class, 'update']);
    Route::delete('educations/{education}', [EducationController::class, 'destroy']);

    Route::put('skills', [CandidateSkillController::class, 'sync']);

    Route::post('cv', [GeneratedCvController::class, 'store']);
    Route::get('cv', [GeneratedCvController::class, 'index']);
});
