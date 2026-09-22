<?php

namespace Tests\Feature;

use App\Enums\ContractType;
use App\Enums\OfferSector;
use App\Models\CandidateProfile;
use App\Models\JobOffer;
use App\Models\Skill;
use App\Services\MatchScorer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La regle de correspondance, extraite de JobOfferMatchService et desormais
 * partagee avec la pile Decouvrir.
 *
 * Les 16 cas d'origine restent couverts par JobOfferMatchServiceTest, qui
 * n'a PAS ete modifie : c'est lui qui prouve l'absence de regression de
 * l'extraction (si le scorer se trompait, ce sont ces tests-la qui
 * tomberaient). On verifie ici les memes regles appelees directement, plus
 * les deux comportements nouveaux : la preference structuree et le score.
 */
class MatchScorerTest extends TestCase
{
    use RefreshDatabase;

    private MatchScorer $scorer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scorer = $this->app->make(MatchScorer::class);
    }

    private function profil(array $attributs = []): CandidateProfile
    {
        return CandidateProfile::factory()->adult()->create($attributs);
    }

    private function offre(array $attributs = []): JobOffer
    {
        return JobOffer::factory()->create($attributs);
    }

    public function test_normalize_strips_accents_and_case(): void
    {
        $this->assertSame('developpeur', $this->scorer->normalize('  Développeur  '));
        $this->assertSame('perpignan', $this->scorer->normalize('PERPIGNAN'));
    }

    public function test_keywords_drop_stopwords_and_short_words(): void
    {
        $mots = $this->scorer->keywordsOf($this->offre(['title' => 'Alternance vendeur en boulangerie H/F']));

        $this->assertContains('vendeur', $mots);
        $this->assertContains('boulangerie', $mots);
        $this->assertNotContains('alternance', $mots);
    }

    public function test_same_city_matches_even_without_a_shared_word(): void
    {
        $profil = $this->profil(['city' => 'Perpignan', 'headline' => 'Plombier']);
        $offre = $this->offre(['title' => 'Boulanger', 'city' => 'PERPIGNAN']);

        $this->assertTrue($this->scorer->matches(
            $profil,
            $offre,
            $this->scorer->keywordsOf($offre),
            $this->scorer->normalize('PERPIGNAN'),
        ));
    }

    public function test_shared_word_family_matches_across_cities(): void
    {
        $profil = $this->profil(['city' => 'Nantes', 'headline' => 'Alternance en commerce']);
        $offre = $this->offre(['title' => 'Assistant commercial', 'city' => 'Perpignan']);

        $this->assertTrue($this->scorer->matches(
            $profil,
            $offre,
            $this->scorer->keywordsOf($offre),
            $this->scorer->normalize('Perpignan'),
        ));
    }

    public function test_a_skill_can_carry_the_shared_word(): void
    {
        $profil = $this->profil(['city' => 'Nantes', 'headline' => 'Etudiant']);
        $profil->skills()->attach(Skill::create(['name' => 'Boulangerie'])->id);

        $offre = $this->offre(['title' => 'Apprenti boulanger', 'city' => 'Perpignan']);

        $this->assertTrue($this->scorer->sharesKeyword($profil->fresh(), $this->scorer->keywordsOf($offre)));
    }

    public function test_explicit_text_preference_excludes_other_contracts(): void
    {
        $profil = $this->profil(['headline' => 'Recherche une alternance']);
        $benevolat = $this->offre(['contract_type' => ContractType::BENEVOLAT]);
        $alternance = $this->offre(['contract_type' => ContractType::ALTERNANCE]);

        $this->assertTrue($this->scorer->contractIsExcluded($profil, $benevolat));
        $this->assertFalse($this->scorer->contractIsExcluded($profil, $alternance));
    }

    public function test_a_silent_profile_is_excluded_from_nothing(): void
    {
        $profil = $this->profil(['headline' => 'Étudiant en BTS', 'bio' => null]);

        foreach (ContractType::cases() as $type) {
            $this->assertFalse(
                $this->scorer->contractIsExcluded($profil, $this->offre(['contract_type' => $type])),
                "Un profil muet ne doit etre exclu d'aucun contrat ({$type->value}).",
            );
        }
    }

    public function test_structured_preference_overrides_text_heuristic(): void
    {
        // Le texte dit « stage », les cases cochees disent « alternance ».
        // Une case cochee est une reponse a la question posee ; le texte
        // n'est qu'un pis-aller pour les profils anterieurs a cet ecran.
        $profil = $this->profil([
            'headline' => 'Recherche un stage en communication',
            'wanted_contract_types' => [ContractType::ALTERNANCE->value],
        ]);

        $this->assertFalse($this->scorer->contractIsExcluded(
            $profil,
            $this->offre(['contract_type' => ContractType::ALTERNANCE]),
        ));
        $this->assertTrue($this->scorer->contractIsExcluded(
            $profil,
            $this->offre(['contract_type' => ContractType::STAGE]),
        ));
    }

    public function test_score_orders_city_then_keyword_then_sector(): void
    {
        $profil = $this->profil([
            'city' => 'Perpignan',
            'headline' => 'Vendeuse',
            'wanted_sectors' => [OfferSector::COMMERCE->value],
        ]);

        $tout = $this->offre(['title' => 'Vendeur conseil', 'city' => 'Perpignan', 'sector' => OfferSector::COMMERCE]);
        $villeSeule = $this->offre(['title' => 'Plombier', 'city' => 'Perpignan', 'sector' => OfferSector::BTP]);
        $motSeul = $this->offre(['title' => 'Vendeur conseil', 'city' => 'Nantes', 'sector' => OfferSector::BTP]);
        $rien = $this->offre(['title' => 'Plombier', 'city' => 'Nantes', 'sector' => OfferSector::BTP]);

        $this->assertSame(4, $this->scorer->score($profil, $tout));
        $this->assertSame(2, $this->scorer->score($profil, $villeSeule));
        $this->assertSame(1, $this->scorer->score($profil, $motSeul));
        $this->assertSame(0, $this->scorer->score($profil, $rien));
    }

    public function test_an_excluded_contract_scores_zero_even_in_the_same_city(): void
    {
        $profil = $this->profil([
            'city' => 'Perpignan',
            'wanted_contract_types' => [ContractType::ALTERNANCE->value],
        ]);

        $offre = $this->offre([
            'title' => 'Vendeur conseil',
            'city' => 'Perpignan',
            'contract_type' => ContractType::BENEVOLAT,
        ]);

        $this->assertSame(0, $this->scorer->score($profil, $offre));
    }
}
