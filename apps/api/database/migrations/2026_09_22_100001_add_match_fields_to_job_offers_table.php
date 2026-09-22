<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Socle du modele match cote offre Jeuncy (MOBILE.md §3.2, §6 et §8).
//
// Les offres n'avaient ni code postal ni coordonnees (seulement location et
// city, migration 2026_07_17_000009). Le code postal devient obligatoire a
// la publication (avec repli sur celui de l'organisation) parce que la
// commune seule est ambigue (homonymes) ; les coordonnees sont geocodees
// cote serveur, jamais saisies par le client.
//
// sector est une string validee par l'enum PHP OfferSector (jamais d'enum
// MySQL sur une nouvelle colonne, voir 2026_07_30_130000).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_offers', function (Blueprint $table) {
            $table->string('postal_code', 10)->nullable();
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            // Rayon de recrutement regle par l'employeur : 5-100 km, defaut 30.
            $table->unsignedSmallInteger('recruitment_radius_km')->default(30);
            $table->string('sector', 40)->nullable();
            // Horaires en texte court ("35h, samedi travaille") et date de debut.
            $table->string('schedule', 255)->nullable();
            $table->date('start_date')->nullable();
            // 16-18 : Decouvrir est reserve aux 16 ans et plus, une offre peut
            // exiger davantage (ex : vente d'alcool).
            $table->unsignedTinyInteger('minimum_age')->default(16);
            // Signale, n'exclut pas (MOBILE.md §6).
            $table->boolean('requires_driving_license')->default(false);
            // Liste de chaines, 8 max, validee par la Form Request.
            $table->json('missions')->nullable();

            // La pile du candidat ne lit que les offres PUBLISHED dans une
            // boite englobante : l'index couvre exactement ce filtre.
            $table->index(['status', 'latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::table('job_offers', function (Blueprint $table) {
            $table->dropIndex(['status', 'latitude', 'longitude']);
            $table->dropColumn([
                'postal_code', 'latitude', 'longitude', 'recruitment_radius_km', 'sector',
                'schedule', 'start_date', 'minimum_age', 'requires_driving_license', 'missions',
            ]);
        });
    }
};
