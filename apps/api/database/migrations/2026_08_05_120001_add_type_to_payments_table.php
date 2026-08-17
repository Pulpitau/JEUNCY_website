<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Deux natures de paiement ponctuel desormais possibles pour une meme offre
    // (voir PaymentService) : publier l'offre, ou debloquer separement l'acces
    // aux candidatures de cette offre. Defaut OFFER_PUBLICATION pour ne pas
    // casser les lignes existantes (seul type possible avant cette migration).
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->enum('type', ['OFFER_PUBLICATION', 'APPLICATIONS_UNLOCK'])
                ->default('OFFER_PUBLICATION')
                ->after('job_offer_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
