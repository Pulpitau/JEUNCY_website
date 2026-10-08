<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Badge « JEUNCY x <ecole> » (feuille de route CFA, 2026-10-07) : un
// candidat inscrit dans un CFA partenaire (ex. IDA) sans alternance peut
// etre rattache a cette ecole, pour rassurer les entreprises partenaires
// du CFA. Lien nullable, pose a la main par un admin (pas d'inscription en
// masse, pas de base CFA importee : voir memoire feuille-de-route-cfa).
// nullOnDelete et non cascade : la suppression d'un CFA ne doit jamais
// supprimer les candidats qu'il a rattaches.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidate_profiles', function (Blueprint $table) {
            $table->foreignId('cfa_organization_id')->nullable()->after('user_id')
                ->constrained('cfa_organizations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('candidate_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cfa_organization_id');
        });
    }
};
