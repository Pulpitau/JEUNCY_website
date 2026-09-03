<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Quand une offre est publiee, les candidats dont le profil correspond sont
// prevenus immediatement (voir JobOfferMatchService).
//
// C'est la reponse retenue a une demande initiale de candidature AUTOMATIQUE
// au nom du candidat : une candidature signifie "je veux ce poste", et
// l'envoyer sans que le candidat l'ait decidee produirait des entretiens ou
// personne n'est demandeur — au detriment premier de l'entreprise qui a paye.
// La notification donne la meme reactivite commerciale avec de vraies
// candidatures.
return new class extends Migration
{
    private const TYPES = [
        'NEW_APPLICATION',
        'APPLICATION_STATUS_CHANGED',
        'PAYMENT_SUCCEEDED',
        'VIDEO_ROOM_INVITE',
        'JOB_OFFER_EXPIRING',
        'TRIAL_OFFERS_ARCHIVED',
        'VIDEO_ROOM_REMINDER',
        'PAYMENT_REFUNDED',
    ];

    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->enum('type', [...self::TYPES, 'JOB_OFFER_MATCH'])->change();
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->enum('type', self::TYPES)->change();
        });
    }
};
