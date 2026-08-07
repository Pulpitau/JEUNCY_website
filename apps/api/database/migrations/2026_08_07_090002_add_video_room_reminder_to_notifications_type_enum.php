<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->enum('type', [
                'NEW_APPLICATION',
                'APPLICATION_STATUS_CHANGED',
                'PAYMENT_SUCCEEDED',
                'VIDEO_ROOM_INVITE',
                'JOB_OFFER_EXPIRING',
                'TRIAL_OFFERS_ARCHIVED',
                'VIDEO_ROOM_REMINDER',
            ])->change();
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->enum('type', [
                'NEW_APPLICATION',
                'APPLICATION_STATUS_CHANGED',
                'PAYMENT_SUCCEEDED',
                'VIDEO_ROOM_INVITE',
                'JOB_OFFER_EXPIRING',
                'TRIAL_OFFERS_ARCHIVED',
            ])->change();
        });
    }
};
