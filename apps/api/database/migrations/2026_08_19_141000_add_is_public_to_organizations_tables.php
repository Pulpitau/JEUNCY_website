<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Presence dans l'annuaire public (/entreprises et /cfa), choisie par
// l'organisation elle-meme.
//
// Meme logique que candidate_profiles.is_visible_in_cvtheque cote candidat :
// etre inscrit sur la plateforme et vouloir figurer dans un annuaire consulte
// par tous sont deux choses distinctes. Cas concret a l'origine du besoin :
// la fiche de demonstration de l'equipe Jeuncy, indispensable pour tester le
// rendu des offres, mais qui n'a rien a faire dans l'annuaire vu par les
// candidats.
//
// Defaut a true : ne change rien pour les fiches existantes ni pour les
// futures inscriptions, ou figurer dans l'annuaire est l'attente normale.
// Masquer une fiche ne masque PAS ses offres publiees — une offre payee doit
// rester visible, c'est ce pour quoi l'entreprise a payé.
return new class extends Migration
{
    public function up(): void
    {
        // Pas de ->after() : l'ordre des colonnes n'a aucun effet fonctionnel,
        // et le suffixer a une colonne ajoutee par une migration ulterieure
        // ferait echouer celle-ci selon l'ordre d'execution.
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('is_public')->default(true);
        });

        Schema::table('cfa_organizations', function (Blueprint $table) {
            $table->boolean('is_public')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('is_public');
        });

        Schema::table('cfa_organizations', function (Blueprint $table) {
            $table->dropColumn('is_public');
        });
    }
};
