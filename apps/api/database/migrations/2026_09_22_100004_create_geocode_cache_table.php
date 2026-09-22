<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Cache du geocodeur (Geoplateforme IGN) par code postal + commune
// normalisee : une commune est geocodee une fois pour tous les profils,
// offres et organisations qui la citent.
//
// latitude / longitude NULLES = le geocodeur n'a pas repondu (panne, timeout,
// commune inconnue). La ligne est gardee avec resolved_at pour ne pas
// marteler le service, et reessayee apres 7 jours (GeocodingService). Une
// panne du geocodeur ne bloque jamais l'utilisateur : la ligne concernee
// reste simplement sans coordonnees jusqu'au rattrapage (geocode:backfill).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geocode_cache', function (Blueprint $table) {
            $table->id();
            $table->string('postal_code', 5);
            // Str::ascii + minuscules + trim, pour que « Perpignan » et
            // « PERPIGNAN » partagent la meme ligne.
            $table->string('city_normalized', 120);
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->timestamp('resolved_at');
            $table->timestamps();

            $table->unique(['postal_code', 'city_normalized']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geocode_cache');
    }
};
