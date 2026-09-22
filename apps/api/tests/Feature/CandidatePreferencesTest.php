<?php

namespace Tests\Feature;

use App\Enums\ContractType;
use App\Enums\DrivingLicenseCategory;
use App\Enums\OfferSector;
use App\Models\CandidateProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * « Ce que je cherche », « Mobilite » et la position GPS (MOBILE.md §3.1 et §6).
 */
class CandidatePreferencesTest extends TestCase
{
    use RefreshDatabase;

    private function profile(array $states = []): CandidateProfile
    {
        $factory = CandidateProfile::factory()->adult();
        foreach ($states as $state) {
            $factory = $factory->{$state}();
        }

        return $factory->create();
    }

    public function test_preferences_are_stored(): void
    {
        $profile = $this->profile();

        $this->actingAs($profile->user, 'api')
            ->putJson('/api/candidate-profile/preferences', [
                'wanted_contract_types' => [ContractType::ALTERNANCE->value],
                'wanted_sectors' => [OfferSector::COMMERCE->value, OfferSector::BTP->value],
                'search_radius_km' => 45,
                'mobility_radius_km' => 20,
                'has_driving_license' => true,
                'driving_license_categories' => [DrivingLicenseCategory::B->value],
                'has_vehicle' => true,
                'pitch' => 'Motivée, disponible dès la rentrée.',
                'show_photo_to_employers' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.search_radius_km', 45);

        $profile->refresh();
        $this->assertSame([ContractType::ALTERNANCE->value], $profile->wanted_contract_types);
        $this->assertSame(20, $profile->mobility_radius_km);
        $this->assertTrue($profile->show_photo_to_employers);
    }

    public function test_preferences_are_validated(): void
    {
        $profile = $this->profile();
        $user = $profile->user;

        $refus = [
            ['wanted_sectors' => [
                OfferSector::COMMERCE->value, OfferSector::BTP->value,
                OfferSector::INDUSTRIE->value, OfferSector::SANTE_SOCIAL->value,
            ]],
            ['search_radius_km' => 4],
            ['mobility_radius_km' => 101],
            ['driving_license_categories' => ['Z']],
            ['wanted_contract_types' => ['INTERIM']],
            ['available_from' => now()->subYear()->toDateString()],
        ];

        foreach ($refus as $payload) {
            $this->actingAs($user, 'api')
                ->putJson('/api/candidate-profile/preferences', $payload)
                ->assertStatus(400)
                ->assertJsonPath('error.code', 'INVALID_INPUT');
        }

        $this->assertNull($profile->fresh()->wanted_sectors);
    }

    public function test_pitch_refuses_email_and_phone(): void
    {
        $user = $this->profile()->user;

        // La phrase est le seul texte libre NOUVEAU montre a un employeur
        // avant le dossier : sans cette garde, l'invariant « ni telephone ni
        // email avant candidature » se contourne en une ligne.
        foreach (['Ecris-moi : lea.girard@example.com', 'Appelle-moi au 06 12 34 56 78'] as $pitch) {
            $this->actingAs($user, 'api')
                ->putJson('/api/candidate-profile/preferences', ['pitch' => $pitch])
                ->assertStatus(400)
                ->assertJsonPath('error.code', 'INVALID_INPUT');
        }

        $this->actingAs($user, 'api')
            ->putJson('/api/candidate-profile/preferences', ['pitch' => 'Sérieuse, dispo dès septembre, permis B.'])
            ->assertOk();
    }

    public function test_device_location_is_rounded_server_side(): void
    {
        $profile = $this->profile();

        $response = $this->actingAs($profile->user, 'api')
            ->putJson('/api/candidate-profile/location', ['latitude' => 42.68871, 'longitude' => 2.89483])
            ->assertOk()
            ->assertJsonPath('data.location_source', 'DEVICE');

        // L'arrondi a ~1 km est promis dans l'ecran de consentement de
        // l'app : une promesse tenue par le client seul n'est pas tenue.
        $profile->refresh();
        $this->assertSame(42.69, $profile->device_latitude);
        $this->assertSame(2.89, $profile->device_longitude);
        $this->assertNotNull($profile->device_located_at);

        // La reponse ne relit jamais la position.
        $this->assertArrayNotHasKey('latitude', $response->json('data'));
        $this->assertArrayNotHasKey('device_latitude', $response->json('data'));
    }

    public function test_gps_never_overwrites_the_profile_position(): void
    {
        $profile = $this->profile(['located']);

        $this->actingAs($profile->user, 'api')
            ->putJson('/api/candidate-profile/location', ['latitude' => 48.85, 'longitude' => 2.35])
            ->assertOk();

        $profile->refresh();
        // Le deck employeur ne lit QUE latitude / longitude : c'est
        // structurel, pas une convention.
        $this->assertSame(42.70, $profile->latitude);
        $this->assertSame(48.85, $profile->device_latitude);
    }

    public function test_delete_location_falls_back_to_profile(): void
    {
        $profile = $this->profile(['located', 'deviceLocated']);

        $this->actingAs($profile->user, 'api')
            ->deleteJson('/api/candidate-profile/location')
            ->assertOk()
            ->assertJsonPath('data.location_source', 'PROFILE');

        $profile->refresh();
        $this->assertNull($profile->device_latitude);
        $this->assertNull($profile->device_located_at);
        $this->assertSame(42.70, $profile->latitude);
    }

    public function test_delete_location_says_null_when_the_profile_has_no_coordinates(): void
    {
        $profile = $this->profile(['deviceLocated']);

        $this->actingAs($profile->user, 'api')
            ->deleteJson('/api/candidate-profile/location')
            ->assertOk()
            ->assertJsonPath('data.location_source', null);
    }

    public function test_location_is_validated(): void
    {
        $user = $this->profile()->user;

        $this->actingAs($user, 'api')
            ->putJson('/api/candidate-profile/location', ['latitude' => 95, 'longitude' => 2.89])
            ->assertStatus(400);
    }

    public function test_owner_sees_coordinates_but_a_serialized_profile_never_does(): void
    {
        $profile = $this->profile(['located']);

        $this->actingAs($profile->user, 'api')
            ->getJson('/api/candidate-profile')
            ->assertOk()
            ->assertJsonPath('data.latitude', 42.70);

        // Le meme profil serialise sans makeVisible (ce que fait
        // ApplicationService::listForOffer) ne porte aucune coordonnee.
        $this->assertArrayNotHasKey('latitude', $profile->fresh()->toArray());
        $this->assertArrayNotHasKey('device_latitude', $profile->fresh()->toArray());
    }

    public function test_preferences_require_an_existing_profile(): void
    {
        $profile = $this->profile();
        $user = $profile->user;
        $profile->delete();

        $this->actingAs($user->fresh(), 'api')
            ->putJson('/api/candidate-profile/preferences', ['search_radius_km' => 30])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'PROFILE_NOT_FOUND');
    }
}
