<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_offers', function (Blueprint $table) {
            $table->enum('payment_status', ['PENDING', 'SUCCEEDED', 'FAILED', 'REFUNDED', 'TRIAL'])
                ->default('PENDING')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('job_offers', function (Blueprint $table) {
            $table->enum('payment_status', ['PENDING', 'SUCCEEDED', 'FAILED', 'REFUNDED'])
                ->default('PENDING')
                ->change();
        });
    }
};
