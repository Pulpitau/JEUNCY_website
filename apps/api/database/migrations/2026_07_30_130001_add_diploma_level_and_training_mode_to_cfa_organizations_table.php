<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cfa_organizations', function (Blueprint $table) {
            $table->string('diploma_level')->nullable()->after('diplomas_offered');
            $table->string('training_mode')->nullable()->after('postal_code');
        });
    }

    public function down(): void
    {
        Schema::table('cfa_organizations', function (Blueprint $table) {
            $table->dropColumn(['diploma_level', 'training_mode']);
        });
    }
};
