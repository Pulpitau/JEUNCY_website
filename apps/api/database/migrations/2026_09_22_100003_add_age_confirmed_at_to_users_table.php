<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// La case « J'ai 15 ans ou plus » de l'inscription etait validee sans etre
// enregistree nulle part (RegisterRequest) : suffisant pour Apple,
// insuffisant pour prouver le consentement. On date desormais l'acceptation.
// Null pour les comptes existants et pour les inscriptions Google (aucune
// case n'y est cochee).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('age_confirmed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('age_confirmed_at');
        });
    }
};
