<?php

namespace Tests\Feature;

use App\Models\CandidateProfile;
use App\Models\GeocodeCache;
use App\Models\JobOffer;
use App\Services\GeocodingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Le geocodeur est le seul appel reseau sur le chemin d'un enregistrement de
 * profil ou d'une publication d'offre : ce qui est verifie ici, c'est d'abord
 * qu'il ne peut pas les faire echouer.
 */
class GeocodingServiceTest extends TestCase
{
    use RefreshDatabase;

    private const PERPIGNAN_LAT = 42.688700;

    private const PERPIGNAN_LNG = 2.894833;

    private GeocodingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->app->make(GeocodingService::class);
    }

    private function repondPerpignan(): void
    {
        // GeoJSON de la Geoplateforme : coordinates = [longitude, latitude].
        $this->geocodeur = fn () => Http::response([
            'type' => 'FeatureCollection',
            'features' => [[
                'geometry' => ['type' => 'Point', 'coordinates' => [self::PERPIGNAN_LNG, self::PERPIGNAN_LAT]],
                'properties' => ['city' => 'Perpignan', 'postcode' => '66000'],
            ]],
        ]);
    }

    public function test_uses_cache_before_http(): void
    {
        GeocodeCache::create([
            'postal_code' => '66000',
            'city_normalized' => 'perpignan',
            'latitude' => 42.70,
            'longitude' => 2.90,
            'resolved_at' => now(),
        ]);

        $coordinates = $this->service->geocode('66000', 'Perpignan');

        $this->assertSame(42.70, $coordinates['lat']);
        $this->assertSame(2.90, $coordinates['lng']);
        Http::assertNothingSent();
    }

    public function test_city_is_normalized_so_one_commune_is_one_cache_line(): void
    {
        $this->repondPerpignan();

        $this->service->geocode('66000', 'Perpignan');
        $this->service->geocode('66000', 'PERPIGNAN ');

        $this->assertSame(1, GeocodeCache::count());
        Http::assertSentCount(1);
    }

    public function test_an_outage_never_throws_and_is_never_cached(): void
    {
        $this->geocodeur = fn () => Http::response('', 503);

        $this->assertNull($this->service->geocode('66000', 'Perpignan'));

        // Une PANNE n'est pas mise en cache : la ligne negative vaudrait
        // sept jours, pendant lesquels geocode:backfill ne rattraperait
        // precisement rien.
        $this->assertNull(GeocodeCache::firstWhere('postal_code', '66000'));
    }

    public function test_a_commune_the_geocoder_does_not_know_is_cached_as_null(): void
    {
        // Le stub par defaut de TestCase repond « aucune commune » : c'est
        // une REPONSE, pas une panne — inutile de la redemander a chaque
        // enregistrement.
        $this->assertNull($this->service->geocode('99999', 'Nulle part'));

        $ligne = GeocodeCache::firstWhere('postal_code', '99999');
        $this->assertNotNull($ligne);
        $this->assertNull($ligne->latitude);
        $this->assertNotNull($ligne->resolved_at);
    }

    public function test_a_row_left_without_coordinates_by_an_outage_is_repaired_by_the_next_backfill(): void
    {
        $this->geocodeur = fn () => Http::response('', 503);
        $profile = CandidateProfile::factory()->create(['city' => 'Perpignan', 'postal_code' => '66000']);
        $this->service->apply($profile, '66000', 'Perpignan');
        $this->assertNull($profile->fresh()->latitude);

        // Le point du correctif : la panne n'a pas ete mise en cache, donc
        // le rattrapage suivant repare vraiment la ligne. Avec un cache
        // negatif de sept jours, le candidat restait hors de tous les decks
        // pendant une semaine sans que rien ne puisse l'y remettre.
        $this->repondPerpignan();
        $this->artisan('geocode:backfill')->assertExitCode(0);

        $this->assertSame(42.69, $profile->fresh()->latitude);
    }

    public function test_an_outage_is_asked_only_once_per_request(): void
    {
        $this->geocodeur = fn () => Http::response('', 503);

        $this->service->geocode('66000', 'Perpignan');
        $this->service->geocode('66000', 'Perpignan');

        // Rien n'est cache en base sur une panne : sans memoire de requete,
        // une offre express rappellerait trois fois un service muet.
        Http::assertSentCount(1);
    }

    public function test_a_cached_failure_is_retried_after_a_week(): void
    {
        GeocodeCache::create([
            'postal_code' => '66000',
            'city_normalized' => 'perpignan',
            'latitude' => null,
            'longitude' => null,
            'resolved_at' => now()->subDays(GeocodeCache::RETRY_AFTER_DAYS + 1),
        ]);
        $this->repondPerpignan();

        $coordinates = $this->service->geocode('66000', 'Perpignan');

        $this->assertNotNull($coordinates);
        $this->assertSame(1, GeocodeCache::count());
    }

    public function test_a_missing_postal_code_is_never_geocoded(): void
    {
        // La commune seule ne suffit pas : « Saint-Martin » existe des
        // dizaines de fois (MOBILE.md §6).
        $this->assertNull($this->service->geocode(null, 'Saint-Martin'));
        Http::assertNothingSent();
    }

    public function test_candidate_coordinates_are_rounded_to_2_decimals(): void
    {
        $this->repondPerpignan();
        $profile = CandidateProfile::factory()->create(['city' => 'Perpignan', 'postal_code' => '66000']);

        $this->service->apply($profile, '66000', 'Perpignan');

        $this->assertSame(42.69, $profile->fresh()->latitude);
        $this->assertSame(2.89, $profile->fresh()->longitude);
    }

    public function test_an_offer_keeps_the_geocoder_precision(): void
    {
        $this->repondPerpignan();
        $offer = JobOffer::factory()->create();

        $this->service->apply($offer, '66000', 'Perpignan');

        $this->assertSame(self::PERPIGNAN_LAT, $offer->fresh()->latitude);
    }

    public function test_apply_clears_coordinates_when_the_geocoder_finds_nothing(): void
    {
        $offer = JobOffer::factory()->located()->create();

        // Le stub par defaut de TestCase repond « aucune commune ».
        $this->service->apply($offer, '99999', 'Nulle part');

        $this->assertNull($offer->fresh()->latitude);
    }

    public function test_backfill_is_idempotent(): void
    {
        $this->repondPerpignan();
        $profile = CandidateProfile::factory()->create(['city' => 'Perpignan', 'postal_code' => '66000']);

        $this->artisan('geocode:backfill')->assertExitCode(0);
        $this->assertSame(42.69, $profile->fresh()->latitude);

        // Deuxieme passe : la ligne a deja des coordonnees, elle n'est plus
        // selectionnee — et le geocodeur n'est pas rappele.
        Http::fake();
        $this->artisan('geocode:backfill')->assertExitCode(0);
        Http::assertNothingSent();
    }

    public function test_backfill_refuses_an_unknown_kind_of_row(): void
    {
        // « --only=profile » au singulier : la faute de frappe la plus
        // probable. Elle affichait « 0 ligne(s) » et sortait en succes,
        // c'est-a-dire qu'elle mentait a l'operateur.
        $this->artisan('geocode:backfill', ['--only' => 'profile'])->assertExitCode(1);
    }

    public function test_backfill_can_be_limited_to_one_kind_of_row(): void
    {
        $this->repondPerpignan();
        $profile = CandidateProfile::factory()->create(['city' => 'Perpignan', 'postal_code' => '66000']);
        $offer = JobOffer::factory()->create(['city' => 'Perpignan', 'postal_code' => '66000']);

        $this->artisan('geocode:backfill', ['--only' => 'offers'])->assertExitCode(0);

        $this->assertNull($profile->fresh()->latitude);
        $this->assertNotNull($offer->fresh()->latitude);
    }
}
