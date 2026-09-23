<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// MATCH_REMINDER : les relances du modele match (MOBILE.md §5, lot 4).
//
// Un seul type pour toute la cascade, et non un par etage. Ce qui distingue
// « ton dossier t'attend » de « l'entreprise ne repond plus » est le MESSAGE,
// pas la nature de l'evenement : cote destinataire c'est toujours « quelqu'un
// te rappelle qu'il se passe quelque chose ». Multiplier les valeurs d'enum
// obligerait a une migration par nuance de texte, sur un enum MySQL qu'il
// faut recreer a chaque fois.
//
// notifications.type est un enum MySQL historique, etendu par ->change()
// comme 2026_09_22_100010. L'ecriture reelle d'une nouvelle valeur en MySQL
// est a verifier au deploiement (le selftest le fait) : SQLite ne le prouve
// pas.
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
        'JOB_OFFER_MATCH',
        'NEW_MATCH',
        'INTEREST_RECEIVED',
        'MATCH_CLOSED',
    ];

    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->enum('type', [...self::TYPES, 'MATCH_REMINDER'])->change();
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->enum('type', self::TYPES)->change();
        });
    }
};
