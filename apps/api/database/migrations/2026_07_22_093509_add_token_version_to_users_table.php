<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Incremente au logout et au reset de mot de passe : invalide
            // immediatement tout access/refresh token deja emis (jusque-la
            // des JWT stateless sans aucun moyen de revocation, voir
            // JwtGuard::user() et AuthService::refreshTokens()/resetPassword()).
            $table->unsignedInteger('token_version')->default(0)->after('is_suspended');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('token_version');
        });
    }
};
