<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_rooms', function (Blueprint $table) {
            // Evite d'envoyer le rappel plusieurs fois pour la meme salle a
            // chaque passage horaire de video-rooms:send-reminders tant que
            // scheduled_at reste dans la fenetre de rappel (1h).
            $table->timestamp('reminder_sent_at')->nullable()->after('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::table('video_rooms', function (Blueprint $table) {
            $table->dropColumn('reminder_sent_at');
        });
    }
};
