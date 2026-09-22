<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Porte de verification des employeurs (MOBILE.md §4.0) et coordonnees des
// organisations (repli geographique des offres sans code postal).
//
// verification_status : PENDING / VERIFIED / REJECTED (App\Enums
// VerificationStatus, string + enum PHP, jamais d'enum MySQL). Tant qu'une
// organisation n'est pas VERIFIED, aucun candidat ne lui est montre.
// Jamais VERIFIED par defaut : une nouvelle fiche nait PENDING, et c'est
// CompanyVerificationService qui la fait passer VERIFIED (automatiquement
// si le SIRET existe, est actif et hors NAF d'enseignement).
//
// verified_by : null = verification automatique. nullOnDelete : un admin
// supprime ne doit pas invalider la verification qu'il a faite.
//
// Les CFA existants (IDA, seul CFA en base a cette date, inscription CFA
// fermee depuis le 2026-09-15) sont consideres verifies : ils sont clients
// et deja en production. Les nouvelles lignes restent PENDING.
return new class extends Migration
{
    private const TABLES = ['companies', 'cfa_organizations'];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            // Pas de ->after() : l'ordre des colonnes n'a aucun effet
            // fonctionnel (lecon de 2026_08_19_141000).
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('verification_status', 20)->default('PENDING');
                $table->timestamp('verified_at')->nullable();
                $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
                // Raison lisible d'un REJECTED ou d'un PENDING ("SIRET
                // invalide", "Verification en attente"...).
                $table->string('verification_note')->nullable();
                $table->decimal('latitude', 9, 6)->nullable();
                $table->decimal('longitude', 9, 6)->nullable();
            });
        }

        // Query builder + now() PHP, et non NOW() SQL : la migration tourne
        // aussi sous SQLite (RefreshDatabase), qui n'a pas cette fonction.
        DB::table('cfa_organizations')->update([
            'verification_status' => 'VERIFIED',
            'verified_at' => now(),
        ]);
    }

    public function down(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('verified_by');
                $table->dropColumn([
                    'verification_status', 'verified_at', 'verification_note', 'latitude', 'longitude',
                ]);
            });
        }
    }
};
