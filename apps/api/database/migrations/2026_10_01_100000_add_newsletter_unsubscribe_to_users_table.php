<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Desinscription de la lettre hebdomadaire.
 *
 * POURQUOI SUR `users` ET NON SUR `candidate_profiles`. La lettre s'adresse
 * aux comptes candidats, profil complete ou non — c'est meme a ceux qui n'ont
 * pas fini leur profil qu'on a le plus de raisons d'ecrire. Une colonne posee
 * sur le profil laisserait donc une partie des destinataires sans aucun moyen
 * de se desinscrire, ce qui est exactement ce que le lien est cense garantir.
 * Elle survit aussi a la suppression d'un profil, et servira telle quelle le
 * jour ou une lettre partira aux entreprises.
 *
 * TIMESTAMP ET NON BOOLEEN : la date EST la preuve. Une opposition se prouve
 * par sa date (RGPD art. 21), et un booleen ne dit pas quand elle a ete
 * exprimee. NULL = toujours abonne.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('newsletter_unsubscribed_at')->nullable()->after('age_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('newsletter_unsubscribed_at');
        });
    }
};
