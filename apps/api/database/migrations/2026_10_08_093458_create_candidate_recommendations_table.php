<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Espace CFA, chantier 2 : un CFA "pousse" un de ses candidats
// (candidate_profiles.cfa_organization_id = ce CFA) vers une offre publiee
// d'une de ses entreprises partenaires.
//
// Decisions de Pierre (2026-10-08) : ca ne cree JAMAIS d'interet employeur a
// sa place (pas de faux OfferInterest), seulement une notification + un lien
// vers la carte du candidat. L'employeur reste libre de liker ou non — voir
// CandidateRecommendationController::show, qui reutilise
// InterestService::assertCibleEligible (via DiscoverService::candidatesQuery)
// pour la meme garde que le "Ca m'interesse" normal.
//
// Unique [candidate_profile_id, job_offer_id] : une recommandation repetee
// par erreur ne doit pas spammer l'employeur d'une notification par clic.
return new class extends Migration
{
    public function up(): void
    {
        // Rattrapage : le premier passage (base de dev Clever Cloud) a cree
        // la table puis echoue sur le nom d'index trop long (MySQL ne defait
        // pas le DDL deja execute, meme piege que external_interests,
        // CLAUDE.md lot 1). Aucune donnee ne peut y exister — rien n'ecrit
        // encore dedans — donc on repart propre.
        Schema::dropIfExists('candidate_recommendations');

        Schema::create('candidate_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cfa_organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_offer_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // Nom explicite et court : l'auto-genere (69 caracteres dont les
            // deux noms de colonnes) depasse la limite MySQL de 64 — piege
            // deja rencontre sur external_interests (CLAUDE.md, lot 1).
            $table->unique(['candidate_profile_id', 'job_offer_id'], 'candidate_recommendations_profile_offer_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_recommendations');
    }
};
