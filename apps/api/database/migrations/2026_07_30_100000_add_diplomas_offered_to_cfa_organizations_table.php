<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cfa_organizations', function (Blueprint $table) {
            $table->text('diplomas_offered')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('cfa_organizations', function (Blueprint $table) {
            $table->dropColumn('diplomas_offered');
        });
    }
};
