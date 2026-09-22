<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Notifications du modele match (MOBILE.md §5) :
//  - NEW_MATCH : les deux parties ont dit oui (in-app + email aux deux).
//  - INTEREST_RECEIVED : un employeur s'interesse au candidat (in-app
//    seulement ; l'offre remonte en tete de sa pile).
//  - MATCH_CLOSED : l'offre ou le dossier n'est plus disponible (in-app).
// Les rappels (MATCH_REMINDER...) viennent au lot 4.
//
// notifications.type est un enum MySQL historique, etendu par ->change()
// comme 2026_09_03_090000. L'ecriture reelle d'une nouvelle valeur en MySQL
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
    ];

    private const MATCH_TYPES = ['NEW_MATCH', 'INTEREST_RECEIVED', 'MATCH_CLOSED'];

    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->enum('type', [...self::TYPES, ...self::MATCH_TYPES])->change();
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->enum('type', self::TYPES)->change();
        });
    }
};
