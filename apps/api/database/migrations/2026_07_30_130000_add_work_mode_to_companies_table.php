<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // String simple (pas d'enum DB) : App\Enums\WorkMode reste la
            // seule source de verite pour les valeurs autorisees (validees
            // par Rule::enum dans les Form Requests) — un enum SQLite
            // compile en CHECK constraint et casse les tests des qu'une
            // valeur est ajoutee/retiree.
            $table->string('work_mode')->nullable()->after('postal_code');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('work_mode');
        });
    }
};
