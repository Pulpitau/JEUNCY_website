<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            // Cascade : une candidature n'a pas de sens sans le profil ou l'offre
            // associes (droit a l'effacement RGPD cote candidat, nettoyage cote offre).
            $table->foreignId('candidate_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_offer_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['SENT', 'SEEN', 'INTERVIEW', 'ACCEPTED', 'REJECTED'])->default('SENT');
            $table->text('cover_letter')->nullable();
            $table->timestamps();

            $table->unique(['candidate_profile_id', 'job_offer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
