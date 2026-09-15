<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// FREE : offre publiee sans aucune contrepartie, depuis que Jeuncy est
// gratuit pour les entreprises (2026-09-15, voir config services.jeuncy).
// Distincte de TRIAL (retiree apres 15 jours par ArchiveExpiredTrialOffers)
// et de SUBSCRIPTION (revenu reel derriere) : sans valeur propre, une offre
// gratuite aurait ete soit retiree a tort, soit comptee comme payee.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_offers', function (Blueprint $table) {
            $table->enum('payment_status', ['PENDING', 'SUCCEEDED', 'FAILED', 'REFUNDED', 'TRIAL', 'SUBSCRIPTION', 'FREE'])
                ->default('PENDING')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('job_offers', function (Blueprint $table) {
            $table->enum('payment_status', ['PENDING', 'SUCCEEDED', 'FAILED', 'REFUNDED', 'TRIAL', 'SUBSCRIPTION'])
                ->default('PENDING')
                ->change();
        });
    }
};
