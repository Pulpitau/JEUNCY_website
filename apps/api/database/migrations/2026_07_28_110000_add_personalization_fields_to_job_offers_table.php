<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_offers', function (Blueprint $table) {
            // experience_level / benefits : pertinents pour une offre d'emploi
            // classique (entreprise). diploma_level / training_rhythm :
            // pertinents pour une offre de formation (CFA). Toutes en texte
            // libre nullable (comme compensation) plutot qu'un enum : evite le
            // probleme de CHECK constraint SQLite rencontre sur payment_status/
            // notifications.type, et laisse le frontend proposer des valeurs
            // suggerees sans figer la liste cote base.
            $table->string('experience_level')->nullable()->after('compensation');
            $table->text('benefits')->nullable()->after('experience_level');
            $table->string('diploma_level')->nullable()->after('benefits');
            $table->string('training_rhythm')->nullable()->after('diploma_level');
        });
    }

    public function down(): void
    {
        Schema::table('job_offers', function (Blueprint $table) {
            $table->dropColumn(['experience_level', 'benefits', 'diploma_level', 'training_rhythm']);
        });
    }
};
