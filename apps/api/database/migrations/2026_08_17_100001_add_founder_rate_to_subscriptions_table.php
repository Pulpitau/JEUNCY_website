<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Tarif fondateur : 299€/mois au lieu de 499€ pour les 50 premiers abonnes
    // (entreprises et CFA confondus), acquis a vie tant que l'abonnement n'est
    // pas resilie — decision produit du 2026-08-17.
    //
    // Le drapeau est pose a la souscription et n'est JAMAIS recalcule ensuite :
    // c'est ce qui rend le tarif reellement verrouille. Le montant paye reste
    // par ailleurs lu dans amount_cents, cette colonne sert a l'affichage
    // ("Tarif fondateur") et au decompte des places restantes.
    //
    // Le decompte des 50 places se fait sur les abonnements portant ce drapeau,
    // y compris resilies : une place consommee ne revient pas dans le pot, sinon
    // le compteur public afficherait des places qui se rouvrent et rouvrirait le
    // tarif a des retardataires (voir SubscriptionService::founderSeatsTaken).
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->boolean('is_founder_rate')->default(false)->index();
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('is_founder_rate');
        });
    }
};
