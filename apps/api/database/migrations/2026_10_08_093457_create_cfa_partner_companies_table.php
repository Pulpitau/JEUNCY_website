<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Espace CFA, chantier 2 de la feuille de route (2026-10-07) : les
// entreprises partenaires qu'un CFA declare, pour pouvoir ensuite leur
// recommander un de ses candidats. Toujours un compte Jeuncy VERIFIED (voir
// memoire feuille-de-route-cfa) : jamais un envoi a une entreprise sans
// compte, ce serait contourner la verification qui protege les mineurs.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cfa_partner_companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cfa_organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['cfa_organization_id', 'company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cfa_partner_companies');
    }
};
