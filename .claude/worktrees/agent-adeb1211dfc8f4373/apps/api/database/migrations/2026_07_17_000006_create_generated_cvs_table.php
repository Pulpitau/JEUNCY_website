<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generated_cvs', function (Blueprint $table) {
            $table->id();
            // Cascade : supprime avec le profil candidat qui la possede.
            $table->foreignId('candidate_profile_id')->constrained()->cascadeOnDelete();
            $table->string('file_url');
            $table->timestamp('generated_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_cvs');
    }
};
