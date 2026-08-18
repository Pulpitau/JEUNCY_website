<?php

use App\Http\Controllers\CvthequeController;
use Illuminate\Support\Facades\Route;

// CVtheque : double garde volontaire. Le middleware de role ecarte les
// candidats et les comptes non professionnels (403), et CvthequeService
// verifie l'abonnement actif (402) — un compte entreprise sans abonnement
// passe le premier filtre mais pas le second, et le frontend distingue les
// deux cas pour afficher soit "reserve aux entreprises", soit l'accroche
// vers l'abonnement.
// ADMIN ajoute au 2026-08-18 : l'equipe Jeuncy doit pouvoir consulter la
// CVtheque telle que la voit un client abonne, sans souscrire un abonnement
// de complaisance. Le second filtre le laisse passer via
// SubscriptionService::hasPaidAccess. Acces interne legitime au sens RGPD :
// Jeuncy est deja responsable de traitement de ces donnees.
Route::prefix('cvtheque')->middleware(['auth:api', 'role:COMPANY,CFA,ADMIN'])->group(function () {
    Route::get('access', [CvthequeController::class, 'access']);
    Route::get('/', [CvthequeController::class, 'index']);
    // whereNumber en plus de l'ordre de declaration : ceinture et bretelles
    // pour que /cvtheque/access ne soit jamais avale par ce parametre (meme
    // precaution que job-offers/search).
    Route::get('{candidateProfile}', [CvthequeController::class, 'show'])->whereNumber('candidateProfile');
});
