<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\ContractType;
use App\Enums\ExternalInterestDecision;
use App\Models\ExternalInterest;
use App\Models\ExternalJobOffer;
use App\Models\JobOffer;
use App\Models\OfferInterest;
use App\Models\User;
use App\Services\BlockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\AideMatch;
use Tests\TestCase;

/**
 * La pile du candidat : offres Jeuncy puis offres partenaires.
 *
 * Invariant central verifie ici : cette pile n'est JAMAIS bornee au
 * perimetre departemental. JEUNCY_MATCH_DEPARTEMENTS ne ferme que le cote
 * employeur ; un jeune doit pouvoir s'interesser a une offre ou qu'elle
 * soit.
 */
class DiscoverOffersTest extends TestCase
{
    use AideMatch;
    use RefreshDatabase;

    // Narbonne : ~56 km de Perpignan. Hors d'un rayon de 30 km, dans un
    // rayon de 100 km, et dans un autre departement (11).
    private const NARBONNE_LAT = 43.1836;

    private const NARBONNE_LNG = 3.0040;

    protected function setUp(): void
    {
        parent::setUp();
        $this->chargerLesRoutesDuMatch();
        $this->ouvrirLePerimetre();
    }

    private function pile(User $candidat): array
    {
        $reponse = $this->withToken($this->jeton($candidat))->getJson('/api/discover/offers');
        $reponse->assertOk();

        return $reponse->json('data');
    }

    public function test_radius_uses_device_location_when_present(): void
    {
        // Profil geocode a Perpignan, GPS a Narbonne : c'est le GPS qui doit
        // gagner, donc l'offre de Narbonne entre et celle de Perpignan sort.
        $candidat = $this->candidat(['search_radius_km' => 20]);
        $profil = $this->profilDe($candidat);
        $profil->device_latitude = self::NARBONNE_LAT;
        $profil->device_longitude = self::NARBONNE_LNG;
        $profil->device_located_at = now();
        $profil->saveQuietly();

        $employeur = $this->employeur();
        $perpignan = $this->offrePubliee($employeur, ['title' => 'Offre de Perpignan']);
        $narbonne = JobOffer::factory()->published()
            ->located(self::NARBONNE_LAT, self::NARBONNE_LNG)
            ->create(['company_id' => $employeur->company->id, 'title' => 'Offre de Narbonne', 'postal_code' => '11100']);

        $data = $this->pile($candidat);

        $this->assertSame('DEVICE', $data['meta']['location_source']);
        $this->assertTrue($data['meta']['has_coordinates']);
        $ids = array_column($data['jeuncy'], 'id');
        $this->assertContains($narbonne->id, $ids);
        $this->assertNotContains($perpignan->id, $ids);
    }

    public function test_falls_back_to_department_then_france(): void
    {
        // Aucune coordonnee : on retombe sur le departement du code postal.
        $candidat = $this->candidat(['city' => 'Perpignan', 'postal_code' => '66000']);
        $profil = $this->profilDe($candidat);
        $profil->latitude = null;
        $profil->longitude = null;
        $profil->saveQuietly();

        $employeur = $this->employeur();
        $dans66 = $this->offrePubliee($employeur, ['postal_code' => '66600']);

        $data = $this->pile($candidat);
        $this->assertFalse($data['meta']['has_coordinates']);
        $this->assertSame('department', $data['meta']['scope']);
        $this->assertSame([$dans66->id], array_column($data['jeuncy'], 'id'));
        $this->assertNull($data['jeuncy'][0]['distance_km']);

        // Plus rien dans le 66 : la pile s'ouvre a toute la France plutot
        // que de rester vide.
        $dans66->delete();
        $ailleurs = $this->offrePubliee($employeur, ['postal_code' => '35000', 'city' => 'Rennes']);

        $data = $this->pile($candidat);
        $this->assertSame('france', $data['meta']['scope']);
        $this->assertSame([$ailleurs->id], array_column($data['jeuncy'], 'id'));
    }

    public function test_pile_is_never_bounded_by_perimeter(): void
    {
        // Perimetre ferme partout : le cote employeur serait bloque, la pile
        // du candidat ne doit pas l'etre.
        $this->ouvrirLePerimetre('');

        $candidat = $this->candidat();
        $offre = $this->offrePubliee($this->employeur());

        $data = $this->pile($candidat);

        $this->assertSame([$offre->id], array_column($data['jeuncy'], 'id'));
    }

    public function test_decided_and_blocked_offers_are_excluded(): void
    {
        $candidat = $this->candidat();
        $profil = $this->profilDe($candidat);
        $employeur = $this->employeur();

        $visible = $this->offrePubliee($employeur, ['title' => 'Encore a decider']);
        $passee = $this->offrePubliee($employeur, ['title' => 'Deja passee']);
        $fermee = $this->offrePubliee($employeur, ['title' => 'Fermee']);

        OfferInterest::factory()->candidatePassed()
            ->create(['candidate_profile_id' => $profil->id, 'job_offer_id' => $passee->id]);
        OfferInterest::factory()->closed()
            ->create(['candidate_profile_id' => $profil->id, 'job_offer_id' => $fermee->id]);

        $this->assertSame([$visible->id], array_column($this->pile($candidat)['jeuncy'], 'id'));

        // Blocage : l'offre de cet employeur disparait entierement.
        app(BlockService::class)->block($candidat, $employeur->id);

        $this->assertSame([], $this->pile($candidat)['jeuncy']);
    }

    public function test_contract_preference_filters_the_pile(): void
    {
        $candidat = $this->candidat(['wanted_contract_types' => [ContractType::ALTERNANCE->value]]);
        $employeur = $this->employeur();

        $alternance = $this->offrePubliee($employeur, ['contract_type' => ContractType::ALTERNANCE]);
        $this->offrePubliee($employeur, ['contract_type' => ContractType::BENEVOLAT]);

        $this->assertSame([$alternance->id], array_column($this->pile($candidat)['jeuncy'], 'id'));
    }

    public function test_employer_interested_comes_first_then_score_then_distance(): void
    {
        $candidat = $this->candidat(['city' => 'Perpignan', 'headline' => 'Vendeuse', 'search_radius_km' => 100]);
        $profil = $this->profilDe($candidat);
        $employeur = $this->employeur();

        // Loin, sans rapport, mais l'employeur a dit oui : premiere.
        $interessee = JobOffer::factory()->published()->located(self::NARBONNE_LAT, self::NARBONNE_LNG)
            ->create(['company_id' => $employeur->company->id, 'title' => 'Plombier', 'city' => 'Narbonne']);
        OfferInterest::factory()->employerLiked()
            ->create(['candidate_profile_id' => $profil->id, 'job_offer_id' => $interessee->id]);

        // Meme ville + meme metier : meilleur score.
        $forte = $this->offrePubliee($employeur, ['title' => 'Vendeur conseil', 'city' => 'Perpignan']);
        // Meme ville seulement, et plus loin.
        $faible = JobOffer::factory()->published()->located(self::NARBONNE_LAT, self::NARBONNE_LNG)
            ->create(['company_id' => $employeur->company->id, 'title' => 'Soudeur', 'city' => 'Narbonne']);

        $ids = array_column($this->pile($candidat)['jeuncy'], 'id');

        $this->assertSame([$interessee->id, $forte->id, $faible->id], $ids);
    }

    public function test_max_20_jeuncy_offers(): void
    {
        $candidat = $this->candidat();
        $employeur = $this->employeur();

        JobOffer::factory()->count(25)->published()->located()
            ->create(['company_id' => $employeur->company->id]);

        $this->assertCount(20, $this->pile($candidat)['jeuncy']);
    }

    public function test_each_offer_carries_distance_flags(): void
    {
        $candidat = $this->candidat();
        $profil = $this->profilDe($candidat);
        $employeur = $this->employeur();
        $offre = $this->offrePubliee($employeur);

        OfferInterest::factory()->employerLiked()
            ->create(['candidate_profile_id' => $profil->id, 'job_offer_id' => $offre->id]);

        $carte = $this->pile($candidat)['jeuncy'][0];

        // L'offre est a la meme position que le candidat : distance nulle.
        $this->assertEqualsWithDelta(0, $carte['distance_km'], 0.01);
        $this->assertTrue($carte['employer_interested']);
        $this->assertFalse($carte['already_applied']);
        $this->assertArrayHasKey('company', $carte);
        $this->assertArrayHasKey('skills', $carte);
    }

    public function test_partner_offers_exclude_kept_and_recent_pass(): void
    {
        $candidat = $this->candidat();
        $profil = $this->profilDe($candidat);

        $visible = ExternalJobOffer::factory()->active()->located()->create(['title' => 'Visible']);
        $gardee = ExternalJobOffer::factory()->active()->located()->create(['title' => 'Gardee']);
        $passee = ExternalJobOffer::factory()->active()->located()->create(['title' => 'Passee']);

        ExternalInterest::factory()->create([
            'candidate_profile_id' => $profil->id,
            'external_job_offer_id' => $gardee->id,
            'decision' => ExternalInterestDecision::KEEP,
            'decided_at' => now()->subDays(200),
        ]);
        ExternalInterest::factory()->create([
            'candidate_profile_id' => $profil->id,
            'external_job_offer_id' => $passee->id,
            'decision' => ExternalInterestDecision::PASS,
            'decided_at' => now()->subDays(3),
        ]);

        $ids = array_column($this->pile($candidat)['partner']['data'], 'id');

        $this->assertSame([$visible->id], $ids);
    }

    public function test_partner_pass_reappears_after_60_days(): void
    {
        $candidat = $this->candidat();
        $profil = $this->profilDe($candidat);
        $offre = ExternalJobOffer::factory()->active()->located()->create();

        ExternalInterest::factory()->create([
            'candidate_profile_id' => $profil->id,
            'external_job_offer_id' => $offre->id,
            'decision' => ExternalInterestDecision::PASS,
            'decided_at' => now()->subDays(61),
        ]);

        $partenaires = $this->pile($candidat)['partner']['data'];

        $this->assertSame([$offre->id], array_column($partenaires, 'id'));
    }

    public function test_partner_offers_carry_a_rounded_distance(): void
    {
        $candidat = $this->candidat(['search_radius_km' => 100]);
        ExternalJobOffer::factory()->active()->located(self::NARBONNE_LAT, self::NARBONNE_LNG)->create();

        $carte = $this->pile($candidat)['partner']['data'][0];

        // ~54,4 km entre les deux points de test. Les coordonnees du
        // candidat sont arrondies a deux decimales cote serveur (42.70 /
        // 2.90), d'ou l'ecart avec les 55,78 km Perpignan -> Narbonne
        // mesures sur coordonnees exactes dans DeployController.
        $this->assertEqualsWithDelta(54.4, $carte['distance_km'], 0.5);
        $this->assertSame(round($carte['distance_km'], 1), $carte['distance_km']);
    }

    /**
     * LES DEUX PILES CASCADENT SEPAREMENT.
     *
     * La production ne compte qu'une offre Jeuncy publiee pour 7 779 offres
     * partenaires : des que cette unique offre est ailleurs, la pile Jeuncy
     * bascule sur « france » pour ne pas etre vide. Lier la pile partenaire a
     * cette portee-la envoyait alors vingt offres tirees de toute la France,
     * triees par date et sans distance, a un candidat qui avait demande
     * 30 km.
     */
    public function test_partner_scope_is_independent_of_the_jeuncy_scope(): void
    {
        $candidat = $this->candidat(['search_radius_km' => 30]);
        $employeur = $this->employeur();

        // Unique offre Jeuncy, a Paris : hors rayon, donc la pile Jeuncy
        // retombe sur toute la France.
        JobOffer::factory()->published()->located(48.8566, 2.3522)
            ->create(['company_id' => $employeur->company->id]);

        $proche = ExternalJobOffer::factory()->active()->located()->create(['title' => 'Proche']);
        $loin = ExternalJobOffer::factory()->active()->located(48.8566, 2.3522)
            ->create(['title' => 'Loin', 'published_at' => now()]);

        $data = $this->pile($candidat);

        $this->assertSame('france', $data['meta']['scope']);
        $this->assertSame('radius', $data['meta']['partner_scope']);
        $this->assertSame([$proche->id], array_column($data['partner']['data'], 'id'));
        $this->assertNotContains($loin->id, array_column($data['partner']['data'], 'id'));
        $this->assertNotNull($data['partner']['data'][0]['distance_km']);
    }

    // Reciproque : sans rien a portee, la pile partenaire s'ouvre elle aussi,
    // sinon un candidat isole resterait devant un ecran vide.
    public function test_partner_pile_falls_back_when_nothing_is_in_range(): void
    {
        $candidat = $this->candidat(['search_radius_km' => 30]);
        $loin = ExternalJobOffer::factory()->active()->located(48.8566, 2.3522)->create();

        $data = $this->pile($candidat);

        $this->assertSame('france', $data['meta']['partner_scope']);
        $this->assertSame([$loin->id], array_column($data['partner']['data'], 'id'));
    }

    /**
     * LONGITUDES NEGATIVES ET PASSAGE PAR ZERO. La moitie ouest de la France
     * est a longitude negative, et la boite englobante y encadre un
     * intervalle qui change de signe : une erreur de signe (min/max
     * intervertis, valeur absolue) viderait la pile de tout le grand Ouest
     * sans rien casser ailleurs. Bayonne (-1,47) -> Pau (-0,37) : ~110 km.
     */
    public function test_the_bounding_box_works_west_of_greenwich(): void
    {
        $candidat = $this->candidat(['search_radius_km' => 100], ['email' => 'ouest@example.test']);
        $profil = $this->profilDe($candidat);
        $profil->latitude = 43.49;
        $profil->longitude = -1.47;
        $profil->saveQuietly();

        $employeur = $this->employeur();
        // Pau : dans le rayon de 100 km, a l'est mais toujours en longitude
        // negative.
        $pau = JobOffer::factory()->published()->located(43.30, -0.37)
            ->create(['company_id' => $employeur->company->id]);
        // Toulouse (+1,44) : au-dela des 100 km, et de l'autre cote de zero.
        $toulouse = JobOffer::factory()->published()->located(43.60, 1.44)
            ->create(['company_id' => $employeur->company->id]);

        $data = $this->pile($candidat);

        $this->assertSame('radius', $data['meta']['scope']);
        $this->assertSame([$pau->id], array_column($data['jeuncy'], 'id'));
        $this->assertNotContains($toulouse->id, array_column($data['jeuncy'], 'id'));
        $this->assertEqualsWithDelta(89, $data['jeuncy'][0]['distance_km'], 3.0);
    }

    public function test_quota_is_inactive_while_the_pile_is_thin(): void
    {
        $candidat = $this->candidat();
        $employeur = $this->employeur();
        JobOffer::factory()->count(3)->published()->located()
            ->create(['company_id' => $employeur->company->id]);

        $meta = $this->pile($candidat)['meta'];

        $this->assertSame(20, $meta['quota']['limit']);
        $this->assertSame(0, $meta['quota']['used']);
        $this->assertFalse($meta['quota']['active']);
    }

    public function test_geocoding_is_never_called_for_real(): void
    {
        // Le stub de TestCase couvre data.geopf.fr et preventStrayRequests
        // fait echouer tout appel oublie : parcourir une pile ne doit
        // declencher aucun appel reseau.
        $this->candidat();
        $this->pile($this->candidat(['postal_code' => '66100'], ['email' => 'autre@example.test']));

        Http::assertNothingSent();
    }

    public function test_an_applied_offer_is_not_in_the_pile(): void
    {
        $candidat = $this->candidat();
        $profil = $this->profilDe($candidat);
        $offre = $this->offrePubliee($this->employeur());

        $profil->applications()->create([
            'job_offer_id' => $offre->id,
            'status' => ApplicationStatus::SENT,
        ]);

        $this->assertSame([], $this->pile($candidat)['jeuncy']);
    }
}
