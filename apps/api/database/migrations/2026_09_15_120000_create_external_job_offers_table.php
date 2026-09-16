<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Offres importees de La bonne alternance (decision du 2026-09-15) : remplir
// Jeuncy d'offres en volume, sans jamais y laisser entrer une ecole.
//
// Table SEPAREE de job_offers, a dessein : une offre importee n'a ni
// proprietaire, ni candidatures chez nous, ni statut de paiement, et elle
// est ecrasee chaque nuit par l'import. La meler aux offres Jeuncy aurait
// oblige chaque garde (appartenance, candidature, notification de
// correspondance) a savoir la reconnaitre. Deux tables, deux mondes ; la
// recherche publique les presente cote a cote, offres Jeuncy en premier.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_job_offers', function (Blueprint $table) {
            $table->id();
            // 'lba' aujourd'hui ; d'autres sources plus tard sans migration.
            $table->string('source', 20);
            // Cle stable d'une offre chez la source (partenaire + identifiant
            // chez ce partenaire) : c'est elle qui rend l'import idempotent.
            $table->string('external_key', 120);
            $table->string('partner_label', 120)->nullable();
            $table->string('partner_job_id', 120)->nullable();

            $table->string('title');
            $table->text('description');
            $table->string('company_name')->nullable();
            $table->string('company_siret', 14)->nullable();
            $table->string('company_naf', 10)->nullable();
            $table->string('company_naf_label')->nullable();
            $table->string('company_size', 60)->nullable();
            $table->string('company_website')->nullable();

            $table->string('address')->nullable();
            $table->string('postal_code', 5)->nullable();
            $table->string('city')->nullable();
            // 2 ou 3 caracteres (2A, 2B, 971...). C'est le perimetre d'import.
            $table->string('department', 3)->nullable();
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();

            // Memes valeurs que job_offers.work_mode (WorkMode), pour que le
            // filtre public s'applique aux deux listes.
            $table->string('work_mode', 20)->nullable();
            $table->date('contract_start')->nullable();
            $table->unsignedSmallInteger('contract_duration_months')->nullable();
            $table->string('target_diploma_level', 2)->nullable();
            $table->string('target_diploma_label')->nullable();
            $table->json('rome_codes')->nullable();
            $table->unsignedSmallInteger('opening_count')->nullable();

            $table->string('apply_url', 1000);
            $table->boolean('is_delegated')->default(false);

            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            // ACTIVE : visible. EXCLUDED : gardee avec sa raison pour que
            // l'admin puisse verifier que le filtre ne jette pas de bonnes
            // offres — un filtre qu'on ne peut pas auditer est un filtre
            // qu'on ne peut pas ajuster.
            $table->string('status', 20)->default('ACTIVE');
            $table->string('exclusion_reason')->nullable();
            // Identifiant de la passe d'import qui a vu cette offre en dernier :
            // ce qu'une passe complete n'a pas vu est supprime a sa fin. Un
            // identifiant plutot qu'une date : deux passes dans la meme seconde
            // (tests, relance manuelle) ne doivent pas se confondre.
            $table->string('import_batch', 36);
            $table->timestamp('last_seen_at');
            $table->timestamps();

            $table->unique(['source', 'external_key']);
            $table->index(['status', 'published_at']);
            $table->index(['status', 'department']);
            $table->index('company_siret');
        });

        // Employeurs ecartes a la main depuis l'admin (une ecole passee entre
        // les mailles). Par SIRET quand on l'a, par nom normalise sinon.
        Schema::create('external_employer_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('siret', 14)->nullable()->unique();
            $table->string('normalized_name')->nullable()->unique();
            $table->string('display_name');
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_employer_blocks');
        Schema::dropIfExists('external_job_offers');
    }
};
