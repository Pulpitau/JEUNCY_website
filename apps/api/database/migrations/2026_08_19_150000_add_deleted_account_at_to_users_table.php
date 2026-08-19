<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Marqueur explicite des comptes supprimes par leur titulaire mais conserves
// pour obligation comptable (voir AccountService::deleteAccount).
//
// Remplace la deduction a partir de l'email (@jeuncy.invalid), qui etait
// fausse a deux titres :
//
//  1. N'importe qui pouvait s'inscrire avec une adresse de ce domaine —
//     RegisterRequest ne valide que le format RFC, et .invalid n'est reserve
//     que pour la resolution DNS, ce qui ne dit rien de ce qu'un formulaire
//     accepte. Un compte reel devenait alors invisible de la moderation.
//
//  2. L'adresse d'anonymisation etait entierement previsible
//     (compte-supprime-{id}@jeuncy.invalid) et users.email est UNIQUE : en
//     pre-enregistrant cette adresse, un tiers faisait echouer la suppression
//     RGPD d'un compte precis, definitivement.
//
// Un etat doit vivre dans une colonne, pas etre devine a partir d'une chaine
// que l'utilisateur controle.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('deleted_account_at')->nullable();
        });

        // Reprise des comptes deja anonymises. La date exacte de suppression
        // n'a pas ete conservee a l'epoque : on retombe sur updated_at, qui
        // correspond precisement a l'anonymisation puisque c'est la derniere
        // ecriture qu'un tel compte ait pu subir.
        DB::table('users')
            ->where('email', 'like', '%@jeuncy.invalid')
            ->update(['deleted_account_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('deleted_account_at');
        });
    }
};
