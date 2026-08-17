<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Visibilite du profil dans la CVtheque payante ouverte aux entreprises et
    // CFA abonnes. Defaut true (visible) : decision produit du 2026-08-17, la
    // CVtheque doit etre peuplee des le lancement pour justifier l'abonnement.
    //
    // Le defaut "visible" impose en contrepartie un droit d'opposition reel et
    // facile a exercer (RGPD art. 21) : interrupteur en clair sur /profile,
    // mention a l'inscription, section dediee dans la politique de
    // confidentialite. Sans ces trois elements, ce defaut n'est pas defendable.
    //
    // Index sur la colonne : c'est le premier filtre de CvthequeService::search,
    // applique a chaque requete avant tous les autres.
    public function up(): void
    {
        Schema::table('candidate_profiles', function (Blueprint $table) {
            $table->boolean('is_visible_in_cvtheque')->default(true)->index();
        });
    }

    public function down(): void
    {
        Schema::table('candidate_profiles', function (Blueprint $table) {
            $table->dropColumn('is_visible_in_cvtheque');
        });
    }
};
