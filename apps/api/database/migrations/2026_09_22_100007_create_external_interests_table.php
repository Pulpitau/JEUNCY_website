<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Gestes du candidat sur les offres partenaires (La bonne alternance,
// MOBILE.md §3.2 et §3.5) : KEEP (« Je garde ») ou PASS. Aucun match
// possible : la candidature se fait sur le site de l'employeur.
//
// Denormalisation voulue (company_siret, company_name, title, city,
// apply_url) : LbaImportService supprime chaque nuit les offres absentes de
// l'export. Sans copie, la liste « Gardees » se viderait au premier import.
// external_job_offer_id est donc nullOnDelete, et la ligne survit a l'offre.
//
// candidate_profile_id : cascadeOnDelete, donnee personnelle rattachee au
// profil (droit a l'effacement RGPD).
//
// Un PASS masque l'offre 60 jours de la pile (decided_at) ; done_at marque
// « C'est fait » sur une offre gardee.
return new class extends Migration
{
    public function up(): void
    {
        // Rattrapage : le premier passage en production a cree la table puis
        // echoue sur le nom d index trop long (MySQL ne defait pas le DDL
        // deja execute). Il reste donc une table partielle, sans index et
        // sans ligne dans la table des migrations. Aucune donnee ne peut y
        // exister — rien n ecrit encore dedans — donc on repart propre.
        Schema::dropIfExists('external_interests');

        Schema::create('external_interests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('external_job_offer_id')->nullable()->constrained('external_job_offers')->nullOnDelete();
            // Valeurs de ExternalInterestDecision (KEEP / PASS).
            $table->string('decision', 4);
            $table->string('company_siret', 14)->nullable();
            $table->string('company_name')->nullable();
            $table->string('title')->nullable();
            $table->string('city')->nullable();
            $table->string('apply_url', 1000);
            $table->timestamp('decided_at');
            $table->timestamp('done_at')->nullable();
            $table->timestamps();

            // MySQL admet plusieurs NULL dans un index unique : les lignes
            // dont l'offre a disparu ne se genent pas entre elles.
            // Nom explicite : le nom genere par Laravel
            // (external_interests_candidate_profile_id_external_job_offer_id_unique,
            // 68 caracteres) depasse la limite de 64 de MySQL. SQLite n'a pas
            // cette limite, donc les tests ne l'auraient jamais montre.
            $table->unique(['candidate_profile_id', 'external_job_offer_id'], 'external_interests_profile_offer_unique');
            $table->index(['candidate_profile_id', 'decision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_interests');
    }
};
