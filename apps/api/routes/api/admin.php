<?php

use App\Http\Controllers\Admin\CandidateProfileController;
use App\Http\Controllers\Admin\ExternalJobOfferController;
use App\Http\Controllers\Admin\JobOfferController;
use App\Http\Controllers\Admin\ModerationController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\StatsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VideoRoomController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware(['auth:api', 'role:ADMIN'])->group(function () {
    Route::get('stats', [StatsController::class, 'index']);

    Route::get('users', [UserController::class, 'index']);

    // Correction d'un nom de candidat mal lu par l'import de CV.
    // Bascule candidat <-> membre de l'equipe (voir setStaffRole).
    Route::post('users/{user}/promote-staff', [UserController::class, 'promoteToStaff']);
    Route::post('users/{user}/demote-staff', [UserController::class, 'demoteFromStaff']);

    Route::get('candidate-profiles', [CandidateProfileController::class, 'index']);
    Route::patch('candidate-profiles/{candidateProfile}/name', [CandidateProfileController::class, 'updateName']);
    Route::post('users/{user}/suspend', [UserController::class, 'suspend']);
    Route::post('users/{user}/reactivate', [UserController::class, 'reactivate']);

    Route::get('job-offers', [JobOfferController::class, 'index']);
    // Apercu du rendu public d'une offre quel que soit son statut (brouillon
    // compris) — voir AdminService::previewJobOffer pour pourquoi ce chemin
    // est distinct de l'endpoint public.
    Route::get('job-offers/{jobOffer}/preview', [JobOfferController::class, 'preview']);
    Route::post('job-offers/{jobOffer}/archive', [JobOfferController::class, 'archive']);

    Route::get('payments', [PaymentController::class, 'index']);
    // Mouvement d'argent reel et irreversible cote Stripe : la seule route
    // admin qui agisse sur le compte bancaire. Garde-fous dans
    // PaymentService::refund.
    Route::post('payments/{payment}/refund', [PaymentController::class, 'refund']);

    // Offres importees : audit du filtre des ecoles et blocage d'employeurs.
    Route::get('external-job-offers/stats', [ExternalJobOfferController::class, 'stats']);
    Route::get('external-job-offers', [ExternalJobOfferController::class, 'index']);
    Route::post('external-job-offers/{externalJobOffer}/block-employer', [ExternalJobOfferController::class, 'blockEmployer']);
    Route::post('external-job-offers/{externalJobOffer}/exclude', [ExternalJobOfferController::class, 'exclude']);
    Route::post('external-job-offers/{externalJobOffer}/restore', [ExternalJobOfferController::class, 'restore']);
    Route::get('external-employer-blocks', [ExternalJobOfferController::class, 'blocks']);
    Route::delete('external-employer-blocks/{externalEmployerBlock}', [ExternalJobOfferController::class, 'removeBlock']);
    // Moderation du modele match (MOBILE.md §10, lot 4). Trois files, une
    // par situation ou quelqu'un de l'equipe doit agir vite sur un dossier
    // impliquant un candidat, souvent mineur.
    Route::get('reports', [ModerationController::class, 'reports']);
    Route::post('reports/{report}/handle', [ModerationController::class, 'handleReport']);
    // Le type est dans l'URL : entreprise n° 3 et CFA n° 3 sont deux
    // organisations differentes, et le deviner finirait par verifier la
    // mauvaise.
    Route::get('verifications', [ModerationController::class, 'verifications']);
    Route::post('verifications/{type}/{id}', [ModerationController::class, 'decideVerification'])
        ->whereIn('type', ['COMPANY', 'CFA'])
        ->whereNumber('id');
    Route::get('silent-employers', [ModerationController::class, 'silentEmployers']);

    Route::get('video-rooms', [VideoRoomController::class, 'index']);
    Route::post('video-rooms/{videoRoom}/end', [VideoRoomController::class, 'end']);
});
