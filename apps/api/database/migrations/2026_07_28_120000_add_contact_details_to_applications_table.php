<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            // Nullable en base (candidatures existantes avant cette migration) mais
            // requis par StoreApplicationRequest pour toute nouvelle candidature —
            // permet a l'employeur de contacter directement le candidat sans repasser
            // par la messagerie de la plateforme.
            $table->string('contact_phone')->nullable()->after('cover_letter');
            // nullOnDelete (pas cascade) : si le candidat supprime/archive ce CV plus
            // tard, la candidature deja envoyee reste consultable, seul le lien vers
            // le CV disparait plutot que la candidature entiere.
            $table->foreignId('generated_cv_id')->nullable()->after('contact_phone')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('generated_cv_id');
            $table->dropColumn('contact_phone');
        });
    }
};
