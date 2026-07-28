<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // trial_started_at reste non-null une fois defini : l'essai gratuit
            // n'est utilisable qu'une seule fois, meme apres son expiration (voir
            // JobOfferService::trialAvailable).
            $table->timestamp('trial_started_at')->nullable()->after('postal_code');
            $table->unsignedSmallInteger('trial_offers_count')->default(0)->after('trial_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['trial_started_at', 'trial_offers_count']);
        });
    }
};
