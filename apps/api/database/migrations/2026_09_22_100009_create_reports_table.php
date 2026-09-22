<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Signalements (MOBILE.md §7) depuis toute carte, tout match, toute photo ou
// toute offre, traites par l'equipe sous 24 h.
//
//  - reporter_user_id : cascadeOnDelete, le signalement est une donnee du
//    compte qui l'a emis.
//  - reported_user_id : nullOnDelete, le signalement reste lisible par
//    l'equipe si le compte vise disparait (il documente un comportement).
//  - job_offer_id : nullOnDelete, meme raison, pour un signalement d'offre.
//  - context : CARD / MATCH / PHOTO / OFFER (App\Enums\ReportContext,
//    string + enum PHP). reason : code court ; details : texte libre.
//  - handled_by / handled_at : traitement, nullOnDelete sur l'admin.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reported_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('job_offer_id')->nullable()->constrained('job_offers')->nullOnDelete();
            $table->string('context', 10);
            $table->string('reason', 40);
            $table->text('details')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();

            $table->index(['reporter_user_id', 'created_at']);
            $table->index('handled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
