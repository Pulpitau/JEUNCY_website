<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suivi des relances du modele match (MOBILE.md §5, lot 4).
 *
 * POURQUOI STOCKER L'ETAGE ATTEINT plutot que de le recalculer. La cascade
 * se lit en jours depuis un evenement (J+3, J+7, J+14, J+30), donc on
 * pourrait la deduire des horodatages existants. Mais le cron d'OVH est
 * horaire et peut sauter des passages : une passe manquee decalerait tout,
 * et une passe rejouee enverrait deux fois le meme rappel. Un etage ecrit
 * rend la commande idempotente — chaque relance part une fois, celle qui a
 * ete sautee part au passage suivant, et rien ne part deux fois.
 *
 * Deux tables parce que les deux cascades ne suivent pas le meme objet :
 * l'interet et le match vivent sur offer_interests, le silence de
 * l'employeur devant un dossier vit sur applications — et un dossier peut
 * exister sans interet (candidature depuis le site).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offer_interests', function (Blueprint $table) {
            // Valeur de App\Enums\MatchReminderStage. Chaine et non enum
            // MySQL : la cascade bougera avec les premiers chiffres du
            // pilote, et recreer un enum a chaque ajustement coute une
            // migration pour rien.
            $table->string('reminder_stage', 30)->nullable()->after('employer_notified_at');
            $table->timestamp('reminded_at')->nullable()->after('reminder_stage');

            // La commande cherche les lignes ouvertes dont l'etage courant
            // n'est pas le dernier : sans cet index elle balaierait toute la
            // table a chaque passage horaire.
            $table->index(['closed_at', 'reminder_stage'], 'offer_interests_reminder_idx');
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->string('reminder_stage', 30)->nullable()->after('responded_at');
            $table->timestamp('reminded_at')->nullable()->after('reminder_stage');

            // Le silence se mesure sur responded_at : une candidature deja
            // repondue sort de la cascade, et l'index porte donc sur les deux.
            $table->index(['responded_at', 'reminder_stage'], 'applications_reminder_idx');
        });
    }

    public function down(): void
    {
        Schema::table('offer_interests', function (Blueprint $table) {
            $table->dropIndex('offer_interests_reminder_idx');
            $table->dropColumn(['reminder_stage', 'reminded_at']);
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->dropIndex('applications_reminder_idx');
            $table->dropColumn(['reminder_stage', 'reminded_at']);
        });
    }
};
