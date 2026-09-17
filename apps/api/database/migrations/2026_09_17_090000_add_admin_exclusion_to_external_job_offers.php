<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Exclusion manuelle d'UNE offre importee (et non de tout l'employeur, voir
// external_employer_blocks) : le cas d'une annonce de formation deguisee
// publiee par un employeur par ailleurs legitime, ou d'une annonce qu'un
// administrateur juge deplacee. La date survit aux imports de nuit, qui
// reappliquent l'exclusion apres chaque passe (voir LbaImportService).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_job_offers', function (Blueprint $table) {
            $table->timestamp('excluded_by_admin_at')->nullable()->after('exclusion_reason');
        });
    }

    public function down(): void
    {
        Schema::table('external_job_offers', function (Blueprint $table) {
            $table->dropColumn('excluded_by_admin_at');
        });
    }
};
