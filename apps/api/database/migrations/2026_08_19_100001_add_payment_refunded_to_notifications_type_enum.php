<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Un remboursement doit etre annonce a celui qui l'encaisse : c'est un
// mouvement d'argent sur son compte, et il s'accompagne du depublication de
// son offre (voir PaymentService::refund). Le laisser le decouvrir en
// constatant que son annonce a disparu serait le plus sur moyen de generer
// une reclamation.
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
    ];

    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->enum('type', [...self::TYPES, 'PAYMENT_REFUNDED'])->change();
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->enum('type', self::TYPES)->change();
        });
    }
};
