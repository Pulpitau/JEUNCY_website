<?php

namespace Tests\Feature;

use App\Enums\ContractType;
use App\Enums\JobOfferStatus;
use App\Models\CandidateProfile;
use App\Models\JobOffer;
use App\Models\OfferInterest;
use App\Models\Skill;
use App\Models\User;
use App\Services\BlockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\AideMatch;
use Tests\TestCase;

/**
 * Le deck employeur : eligibilite seule, jamais de score ni de tri par
 * proximite.
 *
 * LE POINT LE PLUS IMPORTANT DE CE FICHIER est test_no_distance_no_city_in_payload :
 * le lieu de residence n'est pas un critere de selection licite (L1132-1).
 * La distance sert a filtrer ce que LES DEUX parties ont declare accepter,
 * elle ne sort jamais et ne classe rien.
 */
class DiscoverCandidatesTest extends TestCase
{
    use AideMatch;
    use RefreshDatabase;

    private const NARBONNE_LAT = 43.1836;

    private const NARBONNE_LNG = 3.0040;

    protected function setUp(): void
    {
        parent::setUp();
        $this->chargerLesRoutesDuMatch();
        $this->ouvrirLePerimetre();
    }

    private function deck(User $employeur, JobOffer $offre): TestResponse
    {
        return $this->withToken($this->jeton($employeur))
            ->getJson('/api/discover/candidates?job_offer_id='.$offre->id);
    }

    private function ids(User $employeur, JobOffer $offre): array
    {
        return array_column($this->deck($employeur, $offre)->assertOk()->json('data.data'), 'id');
    }

    public function test_requires_owned_offer(): void
    {
        $offre = $this->offrePubliee($this->employeur());
        $autre = $this->employeur();

        $this->deck($autre, $offre)
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_requires_verified_company(): void
    {
        $employeur = $this->employeur(verifiee: false);
        $offre = $this->offrePubliee($employeur);

        $this->deck($employeur, $offre)
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'COMPANY_NOT_VERIFIED');
    }

    public function test_an_offer_without_a_postal_code_says_what_to_do(): void
    {
        // Cas reel : l'unique offre publiee de production (IDA) n'a pas de
        // code postal. Lui repondre « pas ouvert dans ce departement »
        // serait faux et sans issue.
        $employeur = $this->employeur();
        $offre = JobOffer::factory()->published()
            ->create(['company_id' => $employeur->company->id, 'postal_code' => null]);

        $this->deck($employeur, $offre)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'JOB_OFFER_NOT_LOCATED');
    }

    /**
     * Un brouillon n'ouvre pas de deck. Sans cette garde, une entreprise
     * creait une offre jamais publiee et parcourait quand meme les cartes ;
     * un « Ca m'interesse » pose de la envoyait le candidat sur /offres/{id},
     * que PublicJobOfferController refuse.
     */
    public function test_a_draft_offer_has_no_deck(): void
    {
        $employeur = $this->employeur();
        $brouillon = JobOffer::factory()->located()->create([
            'company_id' => $employeur->company->id,
            'status' => JobOfferStatus::DRAFT,
            'postal_code' => '66000',
        ]);

        $this->deck($employeur, $brouillon)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'JOB_OFFER_NOT_PUBLISHED');
    }

    public function test_requires_open_department(): void
    {
        $this->ouvrirLePerimetre('11');
        $employeur = $this->employeur();
        $offre = $this->offrePubliee($employeur, ['postal_code' => '66000']);

        $this->deck($employeur, $offre)
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'MATCH_NOT_OPEN_HERE');
    }

    public function test_an_empty_perimeter_closes_the_employer_side(): void
    {
        $this->ouvrirLePerimetre('');
        $employeur = $this->employeur();
        $offre = $this->offrePubliee($employeur);

        $this->deck($employeur, $offre)
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'MATCH_NOT_OPEN_HERE');
    }

    public function test_eligibility_each_criterion(): void
    {
        $employeur = $this->employeur();
        $offre = $this->offrePubliee($employeur, [
            'contract_type' => ContractType::ALTERNANCE,
            'minimum_age' => 18,
            'recruitment_radius_km' => 30,
        ]);

        $retenu = $this->profilDe($this->candidat(['wanted_contract_types' => [ContractType::ALTERNANCE->value]]));

        $cache = $this->profilDe($this->candidat(['is_visible_in_cvtheque' => false]));
        $sansDate = CandidateProfile::factory()->located()
            ->create(['user_id' => User::factory()->candidate(), 'birth_date' => null]);
        $tropJeune = CandidateProfile::factory()->aged(17)->located()
            ->create(['user_id' => User::factory()->candidate()]);
        // GPS seul : le deck employeur ne lit jamais device_*.
        $gpsSeul = CandidateProfile::factory()->adult()->deviceLocated()
            ->create(['user_id' => User::factory()->candidate()]);
        $mauvaisContrat = $this->profilDe($this->candidat(['wanted_contract_types' => [ContractType::STAGE->value]]));
        // Mobilite trop courte pour venir de Narbonne.
        $tropLoin = CandidateProfile::factory()->adult()->located(self::NARBONNE_LAT, self::NARBONNE_LNG)
            ->create(['user_id' => User::factory()->candidate(), 'mobility_radius_km' => 20]);
        $suspendu = $this->profilDe($this->candidat([], ['is_suspended' => true]));

        $ids = $this->ids($employeur, $offre);

        $this->assertContains($retenu->id, $ids);
        foreach ([
            'invisible en CVtheque' => $cache->id,
            'sans date de naissance' => $sansDate->id,
            'moins de 18 ans (minimum_age de l offre)' => $tropJeune->id,
            'position GPS seulement' => $gpsSeul->id,
            'cherche un stage' => $mauvaisContrat->id,
            'mobilite trop courte' => $tropLoin->id,
            'compte suspendu' => $suspendu->id,
        ] as $raison => $id) {
            $this->assertNotContains($id, $ids, "Ne doit pas apparaitre : {$raison}.");
        }
    }

    public function test_a_recruitment_radius_shorter_than_the_candidate_mobility_also_excludes(): void
    {
        // Les DEUX rayons doivent couvrir : l'employeur qui ne recrute qu'a
        // 10 km ne voit pas quelqu'un pret a faire 100 km.
        $employeur = $this->employeur();
        $offre = $this->offrePubliee($employeur, ['recruitment_radius_km' => 10]);

        CandidateProfile::factory()->adult()->located(self::NARBONNE_LAT, self::NARBONNE_LNG)
            ->create(['user_id' => User::factory()->candidate(), 'mobility_radius_km' => 100]);

        $this->assertSame([], $this->ids($employeur, $offre));
    }

    public function test_blocked_in_both_directions_is_excluded(): void
    {
        $employeur = $this->employeur();
        $offre = $this->offrePubliee($employeur);

        $bloqueParLui = $this->candidat([], ['email' => 'a@example.test']);
        $quiLeBloque = $this->candidat([], ['email' => 'b@example.test']);

        app(BlockService::class)->block($employeur, $bloqueParLui->id);
        app(BlockService::class)->block($quiLeBloque, $employeur->id);

        $ids = $this->ids($employeur, $offre);

        $this->assertNotContains($this->profilDe($bloqueParLui)->id, $ids);
        $this->assertNotContains($this->profilDe($quiLeBloque)->id, $ids);
    }

    public function test_already_decided_candidates_leave_the_deck(): void
    {
        $employeur = $this->employeur();
        $offre = $this->offrePubliee($employeur);

        $vu = $this->profilDe($this->candidat([], ['email' => 'vu@example.test']));
        $passeParLeCandidat = $this->profilDe($this->candidat([], ['email' => 'passe@example.test']));
        $encore = $this->profilDe($this->candidat([], ['email' => 'encore@example.test']));

        OfferInterest::factory()->employerPassed()
            ->create(['candidate_profile_id' => $vu->id, 'job_offer_id' => $offre->id]);
        // Un candidat qui a passe l'offre n'a rien a faire dans le deck : un
        // LIKE sur lui ne produirait ni match ni notification.
        OfferInterest::factory()->candidatePassed()
            ->create(['candidate_profile_id' => $passeParLeCandidat->id, 'job_offer_id' => $offre->id]);

        $this->assertSame([$encore->id], $this->ids($employeur, $offre));
    }

    public function test_no_distance_no_city_in_payload(): void
    {
        $employeur = $this->employeur();
        $offre = $this->offrePubliee($employeur);
        $this->candidat(['phone' => '0600000000', 'bio' => 'Je vis a Perpignan']);

        $carte = $this->deck($employeur, $offre)->assertOk()->json('data.data.0');

        foreach ([
            'distance_km', 'city', 'postal_code', 'latitude', 'longitude',
            'device_latitude', 'device_longitude', 'last_name', 'birth_date',
            'phone', 'address', 'email', 'user_id', 'bio', 'cv_file_url',
        ] as $interdit) {
            $this->assertArrayNotHasKey($interdit, $carte, "La carte ne doit pas porter « {$interdit} ».");
        }

        $this->assertSame('18-20', $carte['age_band']);
        $this->assertTrue($carte['mobility']['covers_offer']);
    }

    public function test_candidate_liked_first_then_recent(): void
    {
        $employeur = $this->employeur();
        $offre = $this->offrePubliee($employeur);

        $ancien = $this->profilDe($this->candidat([], ['email' => 'ancien@example.test']));
        $recent = $this->profilDe($this->candidat([], ['email' => 'recent@example.test']));
        $interesse = $this->profilDe($this->candidat([], ['email' => 'interesse@example.test']));

        $ancien->forceFill(['updated_at' => now()->subYear()])->saveQuietly();
        $recent->forceFill(['updated_at' => now()])->saveQuietly();
        $interesse->forceFill(['updated_at' => now()->subYears(2)])->saveQuietly();

        OfferInterest::factory()->candidateLiked()
            ->create(['candidate_profile_id' => $interesse->id, 'job_offer_id' => $offre->id]);

        $ids = $this->ids($employeur, $offre);

        $this->assertSame([$interesse->id, $recent->id, $ancien->id], $ids);
        $this->assertTrue($this->deck($employeur, $offre)->json('data.data.0.candidate_interested'));
    }

    public function test_skills_in_common_are_listed(): void
    {
        $employeur = $this->employeur();
        $offre = $this->offrePubliee($employeur);
        $vente = Skill::create(['name' => 'Vente']);
        $caisse = Skill::create(['name' => 'Caisse']);
        $offre->skills()->attach($vente->id);

        $profil = $this->profilDe($this->candidat());
        $profil->skills()->attach([$vente->id, $caisse->id]);

        $carte = $this->deck($employeur, $offre)->assertOk()->json('data.data.0');

        $this->assertSame(['Vente'], $carte['skills_in_common']);
        $this->assertTrue($carte['skills'][0]['in_common']);
    }

    public function test_contract_filter_works_on_this_driver(): void
    {
        // whereJsonContains / whereJsonLength : MySQL compile JSON_CONTAINS,
        // SQLite json_each. Ce test existe pour que le jour ou l'un des deux
        // cesse de fonctionner, on l'apprenne ici et non en production.
        $employeur = $this->employeur();
        $offre = $this->offrePubliee($employeur, ['contract_type' => ContractType::ALTERNANCE]);

        $sansPreference = $this->profilDe($this->candidat([], ['email' => 'muet@example.test']));
        $listeVide = $this->profilDe($this->candidat(['wanted_contract_types' => []], ['email' => 'vide@example.test']));
        $bonContrat = $this->profilDe($this->candidat(
            ['wanted_contract_types' => [ContractType::ALTERNANCE->value, ContractType::STAGE->value]],
            ['email' => 'bon@example.test'],
        ));
        $mauvais = $this->profilDe($this->candidat(
            ['wanted_contract_types' => [ContractType::BENEVOLAT->value]],
            ['email' => 'mauvais@example.test'],
        ));

        $ids = $this->ids($employeur, $offre);

        $this->assertContains($sansPreference->id, $ids);
        $this->assertContains($listeVide->id, $ids);
        $this->assertContains($bonContrat->id, $ids);
        $this->assertNotContains($mauvais->id, $ids);
    }

    public function test_a_cfa_reads_the_deck_of_its_own_offer(): void
    {
        $cfa = $this->employeurCfa();
        $offre = $this->offrePubliee($cfa);
        $profil = $this->profilDe($this->candidat());

        $this->assertSame([$profil->id], $this->ids($cfa, $offre));
    }
}
