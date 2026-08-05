<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidate_profiles', function (Blueprint $table) {
            // Liens externes (pas d'upload de fichier video : trop lourd pour
            // l'hebergement mutualise OVH — voir CvService pour les autres
            // uploads, tous des images/PDF de quelques Mo max).
            $table->string('video_url')->nullable()->after('driving_license');
            $table->string('portfolio_url')->nullable()->after('video_url');
            $table->string('linkedin_url')->nullable()->after('portfolio_url');
        });
    }

    public function down(): void
    {
        Schema::table('candidate_profiles', function (Blueprint $table) {
            $table->dropColumn(['video_url', 'portfolio_url', 'linkedin_url']);
        });
    }
};
