<?php

use App\Http\Controllers\ContactController;
use Illuminate\Support\Facades\Route;

// Public et sans authentification : c'est le point d'entree des prospects qui
// n'ont pas encore de compte, tout l'interet de la page.
Route::get('contact', [ContactController::class, 'details']);

// throttle:5,10 — cinq envois par tranche de dix minutes et par IP. Un endpoint
// public qui declenche un email est une cible de spam evidente : sans plafond,
// un robot peut noyer bonjour@jeuncy.com et bruler le quota Resend. Cinq laisse
// largement de quoi se reprendre apres une faute de frappe.
Route::post('contact', [ContactController::class, 'send'])->middleware('throttle:5,10');
