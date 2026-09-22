<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// La pile partenaire du candidat filtre les offres ACTIVE par boite
// englobante sur latitude / longitude avant la haversine. Ces colonnes
// existent depuis 2026_09_15_120000 mais n'etaient pas indexees : sur
// ~8 000 offres actives, le filtre balayait toute la table.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_job_offers', function (Blueprint $table) {
            $table->index(['status', 'latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::table('external_job_offers', function (Blueprint $table) {
            $table->dropIndex(['status', 'latitude', 'longitude']);
        });
    }
};
