<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cfa_organizations', function (Blueprint $table) {
            // Miroir de companies.trial_started_at / trial_offers_count (voir
            // JobOfferService) : l'essai gratuit est desormais aussi ouvert aux CFA.
            $table->timestamp('trial_started_at')->nullable()->after('postal_code');
            $table->unsignedSmallInteger('trial_offers_count')->default(0)->after('trial_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('cfa_organizations', function (Blueprint $table) {
            $table->dropColumn(['trial_started_at', 'trial_offers_count']);
        });
    }
};
