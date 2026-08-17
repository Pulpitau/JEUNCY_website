<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            // Alternative a generated_cv_id : le candidat peut importer un fichier PDF
            // au lieu de choisir un CV genere sur la plateforme. Les deux restent
            // mutuellement exclusifs (voir ApplicationService::applyForUser), jamais
            // les deux renseignes en meme temps.
            $table->string('cv_file_url')->nullable()->after('generated_cv_id');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('cv_file_url');
        });
    }
};
