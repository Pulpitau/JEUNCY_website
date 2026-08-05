<?php

use App\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::prefix('subscriptions')->middleware(['auth:api', 'role:COMPANY,CFA'])->group(function () {
    Route::post('checkout', [SubscriptionController::class, 'checkout']);
    Route::get('mine', [SubscriptionController::class, 'mine']);
    Route::post('cancel', [SubscriptionController::class, 'cancel']);
});
