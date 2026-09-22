<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Prouve que TestCase::setUp prete a SQLite les fonctions trigonometriques
// dont la haversine SQL a besoin (SIN, COS, ASIN, SQRT, RADIANS, POWER), et
// que l'expression — celle de DeployController::autourDePerpignan, reprise
// par App\Support\Haversine — donne la bonne distance.
class SqliteTrigonometryTest extends TestCase
{
    use RefreshDatabase;

    // Perpignan (42.6987, 2.8956) -> Canet-en-Roussillon (42.7060, 3.0370) :
    // ~11,6 km a vol d'oiseau.
    private const PERPIGNAN = [42.6987, 2.8956];

    private const CANET = [42.7060, 3.0370];

    private const HAVERSINE_KM = '6371 * 2 * ASIN(SQRT('
        .'POWER(SIN(RADIANS(? - ?) / 2), 2)'
        .' + COS(RADIANS(?)) * COS(RADIANS(?))'
        .' * POWER(SIN(RADIANS(? - ?) / 2), 2)))';

    public function test_haversine_sql_runs_on_sqlite(): void
    {
        $this->assertSame('sqlite', DB::connection()->getDriverName());

        $row = DB::selectOne('SELECT SIN(0) AS s, COS(0) AS c, ASIN(0) AS a, SQRT(4) AS q, RADIANS(180) AS r, POWER(2, 3) AS p, POW(2, 2) AS p2');

        $this->assertEqualsWithDelta(0.0, (float) $row->s, 1e-9);
        $this->assertEqualsWithDelta(1.0, (float) $row->c, 1e-9);
        $this->assertEqualsWithDelta(0.0, (float) $row->a, 1e-9);
        $this->assertEqualsWithDelta(2.0, (float) $row->q, 1e-9);
        $this->assertEqualsWithDelta(M_PI, (float) $row->r, 1e-9);
        $this->assertEqualsWithDelta(8.0, (float) $row->p, 1e-9);
        $this->assertEqualsWithDelta(4.0, (float) $row->p2, 1e-9);
    }

    public function test_perpignan_to_canet_is_about_12_km(): void
    {
        [$lat1, $lng1] = self::PERPIGNAN;
        [$lat2, $lng2] = self::CANET;

        $row = DB::selectOne(
            'SELECT '.self::HAVERSINE_KM.' AS km',
            [$lat2, $lat1, $lat1, $lat2, $lng2, $lng1],
        );

        $this->assertEqualsWithDelta(11.6, (float) $row->km, 0.5);
    }

    public function test_distance_to_self_is_zero(): void
    {
        [$lat, $lng] = self::PERPIGNAN;

        $row = DB::selectOne(
            'SELECT '.self::HAVERSINE_KM.' AS km',
            [$lat, $lat, $lat, $lat, $lng, $lng],
        );

        $this->assertEqualsWithDelta(0.0, (float) $row->km, 1e-6);
    }
}
