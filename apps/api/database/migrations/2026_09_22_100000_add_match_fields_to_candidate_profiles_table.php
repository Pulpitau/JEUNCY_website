<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Socle du modele match cote candidat (MOBILE.md §3.1, §6 et §8).
//
// Deux paires de coordonnees, a dessein :
//  - latitude / longitude : position PROFILE, geocodee cote serveur a partir
//    de la commune + code postal du profil. C'est la seule que lit le deck
//    employeur (rayon de mobilite), et elle n'est jamais affichee.
//  - device_* : position GPS envoyee par l'app, arrondie a 2 decimales
//    (~1 km) cote serveur. Sert UNIQUEMENT a la pile du candidat : le texte
//    de consentement le promet, la structure le garantit.
//
// La colonne texte driving_license (migration 2026_07_21_151050) est
// conservee : le gabarit CV la lit encore, et la reprise par expression
// reguliere (commande candidates:migrate-driving-license) se relit a la main
// avant d'ecrire dans has_driving_license / driving_license_categories.
//
// Statuts et listes en string/json + enum PHP valide par Rule::enum, jamais
// d'enum MySQL sur une nouvelle colonne (voir 2026_07_30_130000).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidate_profiles', function (Blueprint $table) {
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->decimal('device_latitude', 9, 6)->nullable();
            $table->decimal('device_longitude', 9, 6)->nullable();
            $table->timestamp('device_located_at')->nullable();

            // Rayon de recherche (pile du candidat) et rayon de mobilite
            // (ce que le candidat accepte comme trajet, lu par le deck
            // employeur) : 5-100 km, defaut 30 (MOBILE.md §6).
            $table->unsignedSmallInteger('search_radius_km')->default(30);
            $table->unsignedSmallInteger('mobility_radius_km')->default(30);

            // Valeurs de ContractType et OfferSector (3 secteurs max).
            $table->json('wanted_contract_types')->nullable();
            $table->json('wanted_sectors')->nullable();

            $table->boolean('has_driving_license')->default(false);
            // Valeurs de DrivingLicenseCategory.
            $table->json('driving_license_categories')->nullable();
            $table->boolean('has_vehicle')->default(false);
            $table->date('available_from')->nullable();

            // Phrase de presentation, seul texte libre montre a un employeur
            // avant le dossier.
            $table->string('pitch', 160)->nullable();

            // Opt-in explicite (decision 8 du 2026-09-22) : la photo n'est
            // montree a un employeur que si le candidat l'a demande.
            $table->boolean('show_photo_to_employers')->default(false);

            // La pile et le deck filtrent par boite englobante avant la
            // haversine : l'index rend ce premier filtre exploitable.
            $table->index(['latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::table('candidate_profiles', function (Blueprint $table) {
            $table->dropIndex(['latitude', 'longitude']);
            $table->dropColumn([
                'latitude', 'longitude', 'device_latitude', 'device_longitude', 'device_located_at',
                'search_radius_km', 'mobility_radius_km', 'wanted_contract_types', 'wanted_sectors',
                'has_driving_license', 'driving_license_categories', 'has_vehicle', 'available_from',
                'pitch', 'show_photo_to_employers',
            ]);
        });
    }
};
