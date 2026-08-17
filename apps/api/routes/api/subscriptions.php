<?php

use App\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;

// Etat de l'offre d'ouverture : public et sans authentification, la page
// Tarifs devant afficher le compteur de places restantes a un visiteur qui
// n'a pas encore de compte — c'est tout l'interet de l'argument commercial.
// Declaree AVANT le groupe protege pour ne pas heriter de son auth:api.
Route::get('subscriptions/founder-offer', [SubscriptionController::class, 'founderOffer']);

Route::prefix('subscriptions')->middleware(['auth:api', 'role:COMPANY,CFA'])->group(function () {
    Route::post('checkout', [SubscriptionController::class, 'checkout']);
    Route::get('mine', [SubscriptionController::class, 'mine']);
    Route::post('cancel', [SubscriptionController::class, 'cancel']);
});
