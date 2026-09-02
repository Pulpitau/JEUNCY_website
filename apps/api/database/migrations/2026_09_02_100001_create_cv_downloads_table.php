<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Journal des telechargements de CV depuis la CVtheque.
//
// Exigence RGPD, pas un simple indicateur produit : un CV telecharge quitte
// definitivement la plateforme (le PDF vit ensuite sur le disque du
// recruteur). Si un candidat exerce son droit d'acces ou conteste l'usage de
// ses donnees, Jeuncy doit pouvoir dire QUI a recupere son CV et QUAND —
// sans ce journal, la reponse serait "on ne sait pas", ce qui n'est pas
// tenable pour un responsable de traitement.
//
// Choix de conservation :
//  - restrictOnDelete sur l'utilisateur : on ne veut pas qu'une suppression
//    de compte recruteur efface la tracabilite due au candidat. Les comptes
//    supprimes sont de toute facon anonymises et non effaces
//    (AccountService), la contrainte ne bloquera donc pas le parcours reel.
//  - cascadeOnDelete sur le profil candidat : si le candidat fait supprimer
//    son profil, le journal le concernant part avec lui — c'est son droit a
//    l'effacement qui prime sur notre confort de tracabilite.
//  - source : d'ou venait le PDF servi, pour pouvoir rejouer l'historique
//    meme apres qu'un candidat a remplace son CV.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cv_downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->enum('source', ['UPLOADED', 'GENERATED', 'ON_THE_FLY']);
            $table->timestamp('downloaded_at')->useCurrent();

            // Un recruteur qui reprend le meme CV dix fois n'a pas besoin de
            // dix lignes, mais on garde chaque acces date : c'est justement
            // la frequence qui revele un usage anormal (moissonnage).
            $table->index(['candidate_profile_id', 'downloaded_at']);
            $table->index(['user_id', 'downloaded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cv_downloads');
    }
};
