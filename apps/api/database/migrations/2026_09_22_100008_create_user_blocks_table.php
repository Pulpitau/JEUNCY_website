<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Blocage mutuel (MOBILE.md §7) : applique aux deux decks, a la CVtheque et
// aux candidatures recues, dans les deux sens (bloquer quelqu'un, c'est
// aussi ne plus lui etre montre).
//
// cascadeOnDelete des deux cotes : un blocage n'a plus de sens sans l'un des
// deux comptes. Pas de timestamps Laravel, une seule date de creation.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blocker_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('blocked_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['blocker_user_id', 'blocked_user_id']);
            $table->index('blocked_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_blocks');
    }
};
