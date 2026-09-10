<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Les prelevements mensuels d'abonnement n'etaient enregistres nulle part dans
// `payments` : le chiffre d'affaires du back-office et la page "Mes paiements"
// du client ignoraient donc completement les 299/499 EUR mensuels, alors que
// c'est la principale source de revenus.
//
// Deux changements, une seule migration parce qu'ils servent le meme besoin et
// qu'un fichier de moins est un fichier de moins a oublier lors d'un envoi FTP :
//
//   1. La valeur SUBSCRIPTION dans l'enum `type`. C'est une colonne ENUM cote
//      MySQL : sans cette valeur l'insertion echoue, exactement comme
//      JOB_OFFER_MATCH sur notifications.type le 2026-09-08 — panne muette,
//      quatre allers-retours.
//   2. `stripe_invoice_id`, unique, qui porte l'idempotence : Stripe rejoue ses
//      webhooks, et sans cette contrainte un rejeu creerait une deuxieme ligne
//      de recette pour un seul prelevement.
return new class extends Migration
{
    private const TYPES = ['OFFER_PUBLICATION', 'APPLICATIONS_UNLOCK'];

    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->enum('type', [...self::TYPES, 'SUBSCRIPTION'])
                ->default('OFFER_PUBLICATION')
                ->change();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('stripe_invoice_id')->nullable()->unique()->after('stripe_session_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['stripe_invoice_id']);
            $table->dropColumn('stripe_invoice_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->enum('type', self::TYPES)->default('OFFER_PUBLICATION')->change();
        });
    }
};
