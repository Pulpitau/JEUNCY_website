<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_offers', function (Blueprint $table) {
            $table->enum('contract_type', ['ALTERNANCE', 'SAISONNIER', 'BENEVOLAT', 'JOB_ETUDIANT'])
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('job_offers', function (Blueprint $table) {
            $table->enum('contract_type', ['ALTERNANCE', 'SAISONNIER', 'BENEVOLAT'])->change();
        });
    }
};
