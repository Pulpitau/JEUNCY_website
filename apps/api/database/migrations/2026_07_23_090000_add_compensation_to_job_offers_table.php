<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_offers', function (Blueprint $table) {
            // Texte libre (ex: "800 € / mois", "Selon profil", "SMIC + primes") :
            // plus flexible qu'un montant fixe pour ce que les entreprises
            // veulent afficher.
            $table->string('compensation')->nullable()->after('city');
        });
    }

    public function down(): void
    {
        Schema::table('job_offers', function (Blueprint $table) {
            $table->dropColumn('compensation');
        });
    }
};
