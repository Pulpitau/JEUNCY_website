<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Reutilise le referentiel Skill deja existant (profils candidats) :
        // "competences recherchees" cote entreprise, "competences/experiences
        // acquises" cote CFA sont la meme notion de donnees, seule la copie
        // affichee cote frontend differe selon le type d'offre.
        Schema::create('job_offer_skills', function (Blueprint $table) {
            $table->foreignId('job_offer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->primary(['job_offer_id', 'skill_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_offer_skills');
    }
};
