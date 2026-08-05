<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_offers', function (Blueprint $table) {
            // Chaine libre nullable (pas d'enum DB) : meme convention que
            // diploma_level/training_rhythm sur cette table, evite le probleme
            // de CHECK constraint SQLite dans les tests. Distinct du work_mode/
            // training_mode par defaut de Company/CfaOrganization : une offre
            // precise peut differer du mode habituel de l'organisation.
            $table->string('work_mode')->nullable()->after('city');
        });
    }

    public function down(): void
    {
        Schema::table('job_offers', function (Blueprint $table) {
            $table->dropColumn('work_mode');
        });
    }
};
