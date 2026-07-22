<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_offers', function (Blueprint $table) {
            $table->id();
            // Publiee par une Company OU un CfaOrganization (jamais les deux, jamais
            // aucun) : invariant valide au niveau applicatif, pas en base.
            // Cascade : les offres d'une entreprise/CFA supprime n'ont plus de sens.
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('cfa_organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description');
            $table->enum('contract_type', ['ALTERNANCE', 'SAISONNIER', 'BENEVOLAT']);
            $table->enum('status', ['DRAFT', 'PUBLISHED', 'EXPIRED', 'ARCHIVED'])->default('DRAFT');
            $table->enum('payment_status', ['PENDING', 'SUCCEEDED', 'FAILED', 'REFUNDED'])->default('PENDING');
            $table->string('location')->nullable();
            $table->string('city')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_offers');
    }
};
