<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Role STAFF : membre de l'equipe Jeuncy qui remplit la CVtheque.
//
// Il appelle des candidats et doit verifier qu'ils ont bien termine leur
// inscription. Lui donner l'administration serait disproportionne — il n'a
// besoin ni des statistiques, ni des paiements, ni du pouvoir de suspendre un
// compte. Il consulte la CVtheque comme un client abonne, rien de plus.
return new class extends Migration
{
    private const AVANT = ['CANDIDATE', 'COMPANY', 'CFA', 'ADMIN'];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', [...self::AVANT, 'STAFF'])->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', self::AVANT)->change();
        });
    }
};
