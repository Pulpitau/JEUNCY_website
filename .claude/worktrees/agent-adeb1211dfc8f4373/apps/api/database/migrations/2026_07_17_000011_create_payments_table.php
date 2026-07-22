<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            // Pas de cascade : les paiements sont des pieces comptables a conserver
            // (obligation legale de conservation, prioritaire sur l'effacement RGPD).
            $table->foreignId('user_id')->constrained();
            // SetNull : si l'offre est supprimee, la trace de paiement est conservee.
            $table->foreignId('job_offer_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('amount_cents');
            $table->string('currency')->default('EUR');
            $table->enum('status', ['PENDING', 'SUCCEEDED', 'FAILED', 'REFUNDED'])->default('PENDING');
            $table->string('stripe_payment_intent_id')->nullable()->unique();
            $table->string('stripe_session_id')->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
