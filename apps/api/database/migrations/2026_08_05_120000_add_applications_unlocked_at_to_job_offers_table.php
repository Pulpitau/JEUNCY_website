<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Nouveau modele economique (2026-08-05, demande explicite) : publier une
    // offre et voir les candidatures reçues sont desormais deux actions payantes
    // distinctes (voir aussi payments.type et subscriptions). Cette colonne
    // marque, offre par offre, si l'acces aux candidatures a ete debloque -
    // definitivement une fois vrai, jamais remis a null (voir
    // ApplicationService::listForOffer pour la garde d'acces qui la lit).
    // Toujours renseignee automatiquement pour une offre publiee via l'essai
    // gratuit (JobOfferService::publishViaTrialForUser) : l'essai offre l'acces
    // aux candidatures sans surcout, meme apres l'archivage a 15 jours.
    public function up(): void
    {
        Schema::table('job_offers', function (Blueprint $table) {
            $table->timestamp('applications_unlocked_at')->nullable()->after('payment_status');
        });
    }

    public function down(): void
    {
        Schema::table('job_offers', function (Blueprint $table) {
            $table->dropColumn('applications_unlocked_at');
        });
    }
};
