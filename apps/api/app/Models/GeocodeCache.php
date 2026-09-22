<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Une commune geocodee (code postal + nom normalise). latitude / longitude
// nulles = geocodeur sans reponse, a reessayer apres RETRY_AFTER_DAYS.
#[Fillable(['postal_code', 'city_normalized', 'latitude', 'longitude', 'resolved_at'])]
class GeocodeCache extends Model
{
    use HasFactory;

    protected $table = 'geocode_cache';

    public const RETRY_AFTER_DAYS = 7;

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'resolved_at' => 'datetime',
        ];
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    // Une ligne sans coordonnees est reessayee une fois par semaine ; une
    // ligne resolue est definitive (une commune ne bouge pas).
    public function isStale(): bool
    {
        return ! $this->hasCoordinates()
            && $this->resolved_at !== null
            && $this->resolved_at->lt(now()->subDays(self::RETRY_AFTER_DAYS));
    }
}
