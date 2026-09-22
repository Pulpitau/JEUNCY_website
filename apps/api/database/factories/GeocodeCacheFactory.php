<?php

namespace Database\Factories;

use App\Models\GeocodeCache;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GeocodeCache>
 */
class GeocodeCacheFactory extends Factory
{
    public function definition(): array
    {
        return [
            'postal_code' => '66000',
            'city_normalized' => 'perpignan',
            'latitude' => 42.698,
            'longitude' => 2.895,
            'resolved_at' => now(),
        ];
    }

    // Geocodeur sans reponse : coordonnees nulles, a reessayer apres 7 jours.
    public function unresolved(): static
    {
        return $this->state(fn () => ['latitude' => null, 'longitude' => null]);
    }
}
