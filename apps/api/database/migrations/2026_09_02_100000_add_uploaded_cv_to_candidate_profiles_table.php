<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// CV depose par le candidat lui-meme (son PDF Canva, Word, etc.), conserve
// tel quel. Jusqu'ici l'import de CV ne servait qu'a extraire du texte puis
// jetait le fichier : un candidat qui avait deja un beau CV ne pouvait pas
// le proposer aux recruteurs, seul un CV genere par Jeuncy comptait.
//
// Trois colonnes plutot qu'une seule URL :
//  - cv_file_url          : l'URL publique du fichier stocke
//  - cv_original_filename : le nom d'origine, reaffiche au candidat ("Mon CV
//    2026.pdf" est plus parlant qu'un UUID) et reutilise comme nom de
//    telechargement cote recruteur
//  - cv_uploaded_at       : date de depot, affichee au recruteur pour qu'il
//    sache si le document est recent — un CV de deux ans n'a pas la meme
//    valeur qu'un CV de la semaine
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidate_profiles', function (Blueprint $table) {
            $table->string('cv_file_url')->nullable()->after('photo_url');
            $table->string('cv_original_filename')->nullable()->after('cv_file_url');
            $table->timestamp('cv_uploaded_at')->nullable()->after('cv_original_filename');
        });
    }

    public function down(): void
    {
        Schema::table('candidate_profiles', function (Blueprint $table) {
            $table->dropColumn(['cv_file_url', 'cv_original_filename', 'cv_uploaded_at']);
        });
    }
};
