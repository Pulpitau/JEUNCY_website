<?php

use App\Http\Controllers\CfaPartnershipController;
use Illuminate\Support\Facades\Route;

// Espace CFA, chantier 2 : entreprises partenaires + recommandation d'un
// candidat (voir CfaPartnershipService).
Route::middleware(['auth:api', 'role:CFA'])->group(function () {
    Route::get('cfa/companies/search', [CfaPartnershipController::class, 'searchCompanies']);
    Route::get('cfa/partner-companies', [CfaPartnershipController::class, 'partnerCompanies']);
    Route::post('cfa/partner-companies', [CfaPartnershipController::class, 'addPartnerCompany']);
    Route::delete('cfa/partner-companies/{company}', [CfaPartnershipController::class, 'removePartnerCompany'])->whereNumber('company');
    Route::get('cfa/partner-companies/{company}/job-offers', [CfaPartnershipController::class, 'partnerOffers'])->whereNumber('company');
    Route::get('cfa/candidates', [CfaPartnershipController::class, 'candidates']);
    Route::post('cfa/recommendations', [CfaPartnershipController::class, 'recommend']);
});

// Cote entreprise : les candidats qu'un CFA partenaire lui a recommandes.
Route::middleware(['auth:api', 'role:COMPANY'])->group(function () {
    Route::get('recommendations', [CfaPartnershipController::class, 'received']);
});
