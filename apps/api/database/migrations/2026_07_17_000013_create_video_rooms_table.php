<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_rooms', function (Blueprint $table) {
            $table->id();
            // Cascade : une salle n'a pas de sens sans son hote.
            $table->foreignId('host_id')->constrained('users')->cascadeOnDelete();
            // SetNull : la salle (et son historique) est conservee si le participant
            // est supprime.
            $table->foreignId('participant_id')->nullable()->constrained('users')->nullOnDelete();
            // Genere cote API (UUID non devinable) a la creation, voir CLAUDE.md section 7.
            $table->string('jitsi_room_name')->unique();
            $table->enum('status', ['SCHEDULED', 'LIVE', 'ENDED'])->default('SCHEDULED');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_rooms');
    }
};
