<?php

namespace Tests\Feature;

use App\Enums\ApplicationSource;
use App\Enums\ContractType;
use App\Enums\DrivingLicenseCategory;
use App\Enums\ExternalInterestDecision;
use App\Enums\InterestDecision;
use App\Enums\MatchClosedReason;
use App\Enums\NotificationType;
use App\Enums\OfferSector;
use App\Enums\ReportContext;
use App\Enums\VerificationStatus;
use App\Models\Application;
use App\Models\CandidateProfile;
use App\Models\CfaOrganization;
use App\Models\Company;
use App\Models\ExternalInterest;
use App\Models\ExternalJobOffer;
use App\Models\GeocodeCache;
use App\Models\JobOffer;
use App\Models\Notification;
use App\Models\OfferInterest;
use App\Models\Report;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

// Socle du lot 1 (fondations) : prouve que les 12 migrations du
// 2026-09-22 s'appliquent sur la base de test, que chaque nouvelle colonne
// existe, et que chaque modele nouveau ou modifie sait la lire et l'ecrire
// avec le bon cast.
//
// Ce test ne remplace pas les tests de regles metier (lots A, B, C) : il
// couvre exactement ce que F livre, pour qu'une migration oubliee ou un
// cast manquant se voie ici plutot que trois lots plus loin.
class MatchSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_new_column_exists(): void
    {
        $attendu = [
            'candidate_profiles' => [
                'latitude', 'longitude', 'device_latitude', 'device_longitude', 'device_located_at',
                'search_radius_km', 'mobility_radius_km', 'wanted_contract_types', 'wanted_sectors',
                'has_driving_license', 'driving_license_categories', 'has_vehicle', 'available_from',
                'pitch', 'show_photo_to_employers',
                // La colonne texte historique est conservee : la reprise par
                // expression reguliere se relit a la main avant d'ecrire.
                'driving_license',
            ],
            'job_offers' => [
                'postal_code', 'latitude', 'longitude', 'recruitment_radius_km', 'sector',
                'schedule', 'start_date', 'minimum_age', 'requires_driving_license', 'missions',
            ],
            'companies' => [
                'verification_status', 'verified_at', 'verified_by', 'verification_note',
                'latitude', 'longitude',
            ],
            'cfa_organizations' => [
                'verification_status', 'verified_at', 'verified_by', 'verification_note',
                'latitude', 'longitude',
            ],
            'users' => ['age_confirmed_at'],
            'applications' => ['interest_id', 'source', 'responded_at'],
            'geocode_cache' => ['postal_code', 'city_normalized', 'latitude', 'longitude', 'resolved_at'],
            'offer_interests' => [
                'candidate_profile_id', 'job_offer_id', 'candidate_decision', 'employer_decision',
                'candidate_decided_at', 'employer_decided_at', 'matched_at', 'application_id',
                'closed_at', 'closed_reason', 'candidate_notified_at', 'employer_notified_at',
            ],
            'external_interests' => [
                'candidate_profile_id', 'external_job_offer_id', 'decision', 'company_siret',
                'company_name', 'title', 'city', 'apply_url', 'decided_at', 'done_at',
            ],
            'user_blocks' => ['blocker_user_id', 'blocked_user_id', 'created_at'],
            'reports' => [
                'reporter_user_id', 'reported_user_id', 'job_offer_id', 'context', 'reason',
                'details', 'handled_by', 'handled_at',
            ],
        ];

        foreach ($attendu as $table => $colonnes) {
            $this->assertTrue(Schema::hasTable($table), "table manquante : {$table}");

            foreach ($colonnes as $colonne) {
                $this->assertTrue(
                    Schema::hasColumn($table, $colonne),
                    "colonne manquante : {$table}.{$colonne}",
                );
            }
        }
    }

    public function test_candidate_profile_reads_and_writes_every_new_field(): void
    {
        $profile = CandidateProfile::factory()->adult()->withPreferences()->located(42.70, 2.90)->create();

        $profile->refresh();

        $this->assertSame([ContractType::ALTERNANCE->value], $profile->wanted_contract_types);
        $this->assertSame([OfferSector::COMMERCE->value], $profile->wanted_sectors);
        $this->assertSame([DrivingLicenseCategory::B->value], $profile->driving_license_categories);
        $this->assertSame([ContractType::ALTERNANCE], $profile->wantedContractTypes());
        $this->assertSame([OfferSector::COMMERCE], $profile->wantedSectors());
        $this->assertSame([DrivingLicenseCategory::B], $profile->drivingLicenseCategories());
        $this->assertTrue($profile->has_driving_license);
        $this->assertFalse($profile->has_vehicle);
        $this->assertSame(30, $profile->search_radius_km);
        $this->assertSame(30, $profile->mobility_radius_km);
        $this->assertNotNull($profile->available_from);
        $this->assertSame('Motivee, disponible des la rentree.', $profile->pitch);
        $this->assertFalse($profile->show_photo_to_employers);
        $this->assertSame(42.70, $profile->latitude);
        $this->assertSame(2.90, $profile->longitude);
        $this->assertTrue($profile->hasProfileCoordinates());
        $this->assertFalse($profile->hasDeviceCoordinates());
    }

    public function test_device_location_is_stored_apart_from_profile_location(): void
    {
        $profile = CandidateProfile::factory()->adult()->deviceLocated(43.10, 3.00)->create()->refresh();

        // Le deck employeur ne lit que la position PROFILE : un profil qui
        // n'a que sa position GPS n'a pas de coordonnees exploitables pour
        // lui, et c'est exactement ce que promet le texte de consentement.
        $this->assertFalse($profile->hasProfileCoordinates());
        $this->assertTrue($profile->hasDeviceCoordinates());
        $this->assertSame(43.10, $profile->device_latitude);
        $this->assertNotNull($profile->device_located_at);
    }

    public function test_coordinates_are_hidden_but_visible_to_the_owner(): void
    {
        $profile = CandidateProfile::factory()->adult()->located()->deviceLocated()->create()->refresh();

        $serialise = $profile->toArray();

        foreach (CandidateProfile::OWNER_VISIBLE as $cle) {
            $this->assertArrayNotHasKey($cle, $serialise, "coordonnee exposee : {$cle}");
        }

        $pourLeProprietaire = $profile->makeVisible(CandidateProfile::OWNER_VISIBLE)->toArray();

        foreach (CandidateProfile::OWNER_VISIBLE as $cle) {
            $this->assertArrayHasKey($cle, $pourLeProprietaire, "coordonnee absente de l'export : {$cle}");
        }
    }

    public function test_age_band_boundaries(): void
    {
        $bandes = [];

        foreach ([17, 18, 20, 21, 25, 26] as $age) {
            $bandes[$age] = CandidateProfile::factory()->aged($age)->make()->age_band;
        }

        $this->assertSame('<18', $bandes[17]);
        $this->assertSame('18-20', $bandes[18]);
        $this->assertSame('18-20', $bandes[20]);
        $this->assertSame('21-25', $bandes[21]);
        $this->assertSame('21-25', $bandes[25]);
        $this->assertSame('26+', $bandes[26]);

        // Sans date de naissance, aucune tranche : le presenteur montrera
        // null plutot qu'une tranche inventee.
        $this->assertNull(CandidateProfile::factory()->make()->age_band);

        // La tranche n'est pas dans $appends : elle n'apparait que si le
        // presenteur la demande.
        $this->assertArrayNotHasKey('age_band', CandidateProfile::factory()->aged(20)->make()->toArray());
    }

    public function test_job_offer_reads_and_writes_every_new_field(): void
    {
        $offer = JobOffer::factory()->published()->located(42.70, 2.90)->create([
            'schedule' => '35h, samedi travaille',
            'start_date' => '2026-10-01',
            'minimum_age' => 18,
            'requires_driving_license' => true,
            'missions' => ['Accueil', 'Mise en rayon'],
        ])->refresh();

        $this->assertSame('66000', $offer->postal_code);
        $this->assertSame(OfferSector::COMMERCE, $offer->sector);
        $this->assertSame(30, $offer->recruitment_radius_km);
        $this->assertSame('35h, samedi travaille', $offer->schedule);
        $this->assertSame('2026-10-01', $offer->start_date->toDateString());
        $this->assertSame(18, $offer->minimum_age);
        $this->assertTrue($offer->requires_driving_license);
        $this->assertSame(['Accueil', 'Mise en rayon'], $offer->missions);
        $this->assertTrue($offer->hasCoordinates());
    }

    public function test_coordinates_are_never_mass_assignable(): void
    {
        // Un client ne doit pas pouvoir se declarer ailleurs qu'ou il est :
        // les coordonnees sont ecrites par le geocodage cote serveur.
        //
        // Le test passe par fill() et non par la factory : les factories
        // creent leurs modeles unguarded (Model::unguarded), elles ne
        // prouveraient donc rien sur le mass-assignment.
        $profile = CandidateProfile::factory()->adult()->create()->refresh();
        $profile->fill(['latitude' => 48.85, 'longitude' => 2.35, 'device_latitude' => 48.85]);

        $offer = JobOffer::factory()->create()->refresh();
        $offer->fill(['latitude' => 48.85, 'longitude' => 2.35]);

        $company = Company::factory()->create()->refresh();
        $company->fill([
            'latitude' => 48.85,
            'verification_status' => VerificationStatus::VERIFIED->value,
            'verified_at' => now(),
        ]);

        $this->assertNull($profile->latitude);
        $this->assertNull($profile->longitude);
        $this->assertNull($profile->device_latitude);
        $this->assertNull($offer->latitude);
        $this->assertNull($company->latitude);
        // Et une entreprise ne se declare pas verifiee elle-meme.
        $this->assertSame(VerificationStatus::PENDING, $company->verification_status);
        $this->assertNull($company->verified_at);
    }

    public function test_new_organizations_are_pending_and_hide_verification_details(): void
    {
        $company = Company::factory()->create()->refresh();
        $cfa = CfaOrganization::factory()->create()->refresh();

        // La migration 2026_09_22_100002 ne passe VERIFIED que les lignes
        // CFA existantes (IDA) : une ligne creee ensuite nait PENDING.
        $this->assertSame(VerificationStatus::PENDING, $company->verification_status);
        $this->assertSame(VerificationStatus::PENDING, $cfa->verification_status);
        $this->assertFalse($company->isVerified());
        $this->assertFalse($cfa->isVerified());

        foreach ([$company, $cfa] as $organisation) {
            $public = $organisation->toArray();

            // Signal de confiance montre au candidat...
            $this->assertArrayHasKey('verification_status', $public);
            // ...mais jamais le detail interne ni les coordonnees.
            foreach (['verified_by', 'verification_note', 'latitude', 'longitude'] as $cle) {
                $this->assertArrayNotHasKey($cle, $public, "champ expose : {$cle}");
            }

            $this->assertArrayHasKey('verification_note', $organisation->makeVisible($organisation::OWNER_VISIBLE)->toArray());
        }
    }

    public function test_verified_state_and_coordinates_round_trip_on_organizations(): void
    {
        $company = Company::factory()->verified()->located(42.70, 2.90)->create()->refresh();
        $cfa = CfaOrganization::factory()->verified()->located()->create()->refresh();

        $this->assertTrue($company->isVerified());
        $this->assertTrue($cfa->isVerified());
        $this->assertNotNull($company->verified_at);
        // verified_by null = verification automatique.
        $this->assertNull($company->verified_by);
        $this->assertTrue($company->hasCoordinates());
        $this->assertTrue($cfa->hasCoordinates());
    }

    public function test_offer_interest_casts_relations_and_scopes(): void
    {
        $matched = OfferInterest::factory()->matched()->create();
        $ferme = OfferInterest::factory()->matched()->closed(MatchClosedReason::OFFER_ARCHIVED)->create();
        OfferInterest::factory()->candidatePassed()->create();

        $matched->refresh();
        $ferme->refresh();

        $this->assertSame(InterestDecision::LIKE, $matched->candidate_decision);
        $this->assertSame(InterestDecision::LIKE, $matched->employer_decision);
        $this->assertNotNull($matched->matched_at);
        $this->assertTrue($matched->isMatched());
        $this->assertSame(MatchClosedReason::OFFER_ARCHIVED, $ferme->closed_reason);

        $this->assertInstanceOf(CandidateProfile::class, $matched->candidateProfile);
        $this->assertInstanceOf(JobOffer::class, $matched->jobOffer);
        $this->assertNull($matched->application);

        $this->assertSame(2, OfferInterest::query()->open()->count());
        $this->assertSame(2, OfferInterest::query()->matched()->count());
        $this->assertSame(1, OfferInterest::query()->open()->matched()->count());
    }

    public function test_offer_interest_is_unique_per_couple(): void
    {
        $interest = OfferInterest::factory()->candidateLiked()->create();

        $this->expectException(QueryException::class);

        OfferInterest::factory()->create([
            'candidate_profile_id' => $interest->candidate_profile_id,
            'job_offer_id' => $interest->job_offer_id,
        ]);
    }

    public function test_application_carries_its_source_and_interest(): void
    {
        $interest = OfferInterest::factory()->matched()->create();

        $application = Application::create([
            'candidate_profile_id' => $interest->candidate_profile_id,
            'job_offer_id' => $interest->job_offer_id,
            'interest_id' => $interest->id,
            'source' => ApplicationSource::MATCH,
            'responded_at' => now(),
        ])->refresh();

        $this->assertSame(ApplicationSource::MATCH, $application->source);
        $this->assertNotNull($application->responded_at);
        $this->assertTrue($interest->is($application->interest));

        $interest->update(['application_id' => $application->id]);
        $this->assertTrue($application->is($interest->refresh()->application));

        // Le defaut de la colonne : un dossier ancien reste « SITE ».
        $ancien = Application::create([
            'candidate_profile_id' => $interest->candidate_profile_id,
            'job_offer_id' => JobOffer::factory()->published()->create()->id,
        ])->refresh();
        $this->assertSame(ApplicationSource::SITE, $ancien->source);
    }

    public function test_external_interest_survives_the_deletion_of_its_offer(): void
    {
        $offer = ExternalJobOffer::factory()->active()->located()->create();
        $interest = ExternalInterest::factory()->create(['external_job_offer_id' => $offer->id]);

        $offer->delete();

        $interest->refresh();

        // nullOnDelete : l'import de nuit supprime les offres absentes de
        // l'export, la liste « Gardees » ne doit pas se vider pour autant.
        $this->assertNull($interest->external_job_offer_id);
        $this->assertSame(ExternalInterestDecision::KEEP, $interest->decision);
        $this->assertSame('Au pain dore', $interest->company_name);
        $this->assertNotNull($interest->apply_url);
        $this->assertNotNull($interest->decided_at);
        $this->assertNull($interest->done_at);
    }

    public function test_user_block_and_report_round_trip(): void
    {
        $candidat = User::factory()->candidate()->create();
        $entreprise = User::factory()->company()->create();

        $block = UserBlock::create([
            'blocker_user_id' => $entreprise->id,
            'blocked_user_id' => $candidat->id,
        ])->refresh();

        $this->assertNotNull($block->created_at);
        $this->assertTrue($entreprise->is($block->blocker));
        $this->assertTrue($candidat->is($block->blocked));
        $this->assertSame(1, $entreprise->blocks()->count());
        $this->assertSame(1, $candidat->blockedBy()->count());

        $report = Report::factory()->create([
            'reporter_user_id' => $entreprise->id,
            'reported_user_id' => $candidat->id,
            'context' => ReportContext::MATCH,
        ])->refresh();

        $this->assertSame(ReportContext::MATCH, $report->context);
        $this->assertNull($report->handled_at);

        // Le signalement reste lisible par l'equipe si le compte vise
        // disparait : il documente un comportement.
        $candidat->forceDelete();
        $this->assertNull($report->refresh()->reported_user_id);
    }

    public function test_geocode_cache_marks_unresolved_rows_stale_after_a_week(): void
    {
        $resolu = GeocodeCache::factory()->create()->refresh();
        $this->assertTrue($resolu->hasCoordinates());
        $this->assertFalse($resolu->isStale());

        $recent = GeocodeCache::factory()->unresolved()->create(['postal_code' => '66140'])->refresh();
        $this->assertFalse($recent->hasCoordinates());
        $this->assertFalse($recent->isStale());

        $ancien = GeocodeCache::factory()->unresolved()->create([
            'postal_code' => '66200',
            'resolved_at' => now()->subDays(GeocodeCache::RETRY_AFTER_DAYS + 1),
        ])->refresh();
        $this->assertTrue($ancien->isStale());
    }

    public function test_geocode_cache_is_unique_per_commune(): void
    {
        GeocodeCache::factory()->create();

        $this->expectException(QueryException::class);

        GeocodeCache::factory()->create();
    }

    public function test_age_confirmed_at_is_stored_on_the_user(): void
    {
        $user = User::factory()->candidate()->create(['age_confirmed_at' => now()])->refresh();

        $this->assertNotNull($user->age_confirmed_at);
        $this->assertNull(User::factory()->candidate()->create()->refresh()->age_confirmed_at);
    }

    public function test_the_three_match_notification_types_are_writable(): void
    {
        // notifications.type est un enum MySQL historique, etendu par
        // ->change() (migration 2026_09_22_100010). Sous SQLite, Laravel le
        // traduit en contrainte CHECK : ecrire une des trois nouvelles
        // valeurs prouve que la contrainte a bien ete reconstruite. En
        // MySQL, seul le deploiement le prouve (selftest).
        $user = User::factory()->candidate()->create();

        foreach ([NotificationType::NEW_MATCH, NotificationType::INTEREST_RECEIVED, NotificationType::MATCH_CLOSED] as $type) {
            $notification = Notification::create([
                'user_id' => $user->id,
                'type' => $type,
                'message' => 'Message de test.',
                'link' => '/mes-candidatures',
            ])->refresh();

            $this->assertSame($type, $notification->type);
        }

        $this->assertSame(3, Notification::query()->count());
    }
}
