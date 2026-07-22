<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            // Cascade : les notifications n'ont pas de valeur une fois le compte supprime.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', [
                'NEW_APPLICATION',
                'APPLICATION_STATUS_CHANGED',
                'PAYMENT_SUCCEEDED',
                'VIDEO_ROOM_INVITE',
                'JOB_OFFER_EXPIRING',
            ]);
            $table->string('message');
            $table->string('link')->nullable();
            $table->boolean('read')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
