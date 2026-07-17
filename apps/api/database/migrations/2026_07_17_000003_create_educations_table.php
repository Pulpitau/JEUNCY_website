<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('educations', function (Blueprint $table) {
            $table->id();
            // Cascade : supprime avec le profil candidat qui la possede.
            $table->foreignId('candidate_profile_id')->constrained()->cascadeOnDelete();
            $table->string('degree');
            $table->string('school');
            $table->string('field_of_study')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('educations');
    }
};
