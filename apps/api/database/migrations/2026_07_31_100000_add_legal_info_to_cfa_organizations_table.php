<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cfa_organizations', function (Blueprint $table) {
            $table->string('siret', 14)->nullable()->after('name');
            $table->string('nda_number', 50)->nullable()->after('siret');
            $table->string('qualiopi_number', 50)->nullable()->after('nda_number');
        });
    }

    public function down(): void
    {
        Schema::table('cfa_organizations', function (Blueprint $table) {
            $table->dropColumn(['siret', 'nda_number', 'qualiopi_number']);
        });
    }
};
