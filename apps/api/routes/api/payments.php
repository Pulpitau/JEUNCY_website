<?php

use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

Route::post('job-offers/{jobOffer}/checkout', [PaymentController::class, 'checkout'])
    ->middleware(['auth:api', 'role:COMPANY,CFA']);

// Stripe appelle ce endpoint directement : aucune authentification JWT, la
// signature du corps de requete (verifiee dans PaymentService) en tient lieu.
Route::post('stripe/webhook', [PaymentController::class, 'webhook']);
