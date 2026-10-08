<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// CANDIDATE_RECOMMENDED : un CFA recommande un de ses candidats a une
// entreprise partenaire, pour une offre precise (espace CFA, chantier 2).
//
// Cote employeur uniquement, jamais cote candidat : contrairement a
// INTEREST_RECEIVED, ce n'est pas un interet employeur reel, juste un signal
// "regarde cette carte" — le candidat n'est pas prevenu qu'on l'a recommande.
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
        'MATCH_REMINDER',
    ];

    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->enum('type', [...self::TYPES, 'CANDIDATE_RECOMMENDED'])->change();
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->enum('type', self::TYPES)->change();
        });
    }
};
