<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Abonnement mensuel Stripe (publication illimitee + acces candidatures
    // inclus, voir SubscriptionService) - nouveau modele economique du
    // 2026-08-05, en complement du paiement a l'offre existant (payments).
    // Pas de cascade sur user_id, comme payments : ce sont des pieces
    // comptables a conserver (voir AccountService::deleteAccount, qui
    // anonymise plutot que supprime un compte ayant des abonnements).
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->enum('status', ['ACTIVE', 'CANCELED', 'PAST_DUE'])->default('ACTIVE');
            $table->unsignedInteger('amount_cents');
            $table->string('currency')->default('EUR');
            $table->string('stripe_subscription_id')->unique();
            $table->string('stripe_customer_id')->nullable();
            // Fin de la periode payee en cours - purement informatif (affichage),
            // jamais utilise pour la garde d'acces (voir hasActiveSubscription,
            // qui se base uniquement sur status = ACTIVE tenu a jour par les
            // webhooks Stripe customer.subscription.*).
            $table->timestamp('current_period_end')->nullable();
            // Renseigne des la demande d'annulation (cancel_at_period_end cote
            // Stripe) : status reste ACTIVE jusqu'a la fin de la periode payee,
            // ce champ permet d'afficher "resiliation prevue le ..." en attendant.
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
