<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Rattachement du dossier au modele match (MOBILE.md §5 et §8).
//
//  - interest_id : la ligne offer_interests du couple, nullOnDelete (une
//    ligne d'interet supprimee — annulation dans la fenetre des 5 minutes —
//    ne doit pas emporter un dossier envoye). Creee apres offer_interests,
//    d'ou l'ordre des migrations.
//  - source : SITE (formulaire web), APP (application mobile, en-tete
//    X-Jeuncy-Client) ou MATCH (dossier envoye apres un match). String +
//    enum PHP ApplicationSource, jamais d'enum MySQL sur une nouvelle colonne.
//  - responded_at : premiere fois qu'un employeur change le statut du
//    dossier ; alimente le badge « Repond en N jours ».
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->foreignId('interest_id')->nullable()->constrained('offer_interests')->nullOnDelete();
            $table->string('source', 10)->default('SITE');
            $table->timestamp('responded_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('interest_id');
            $table->dropColumn(['source', 'responded_at']);
        });
    }
};
