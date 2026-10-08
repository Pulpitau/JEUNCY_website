<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Exceptions\ApiException;
use App\Models\CandidateProfile;
use App\Models\CandidateRecommendation;
use App\Models\Company;
use App\Models\JobOffer;
use App\Models\Notification;
use App\Models\User;
use App\Services\CfaPartnershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AideMatch;
use Tests\TestCase;

/**
 * Espace CFA, chantier 2 (2026-10-08) : entreprises partenaires (toujours un
 * compte Jeuncy verifie) et recommandation d'un candidat du CFA pour une
 * offre publiee d'un partenaire.
 *
 * REGLE QUI GOUVERNE TOUT CE FICHIER (decision de Pierre) : une
 * recommandation ne cree JAMAIS d'interet employeur a la place de
 * l'entreprise — seulement une notification. « Ca m'interesse » reste un
 * geste de l'employeur, inchange (POST interests).
 */
class CfaPartnershipServiceTest extends TestCase
{
    use AideMatch;
    use RefreshDatabase;

    private CfaPartnershipService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ouvrirLePerimetre();
        $this->service = $this->app->make(CfaPartnershipService::class);
    }

    // --- Entreprises partenaires ---

    public function test_a_cfa_can_add_and_list_a_verified_partner_company(): void
    {
        $cfa = $this->employeurCfa();
        $company = Company::factory()->verified()->create(['name' => 'NexaTech']);

        $this->service->addPartnerCompany($cfa, $company->id);

        $this->assertSame(['NexaTech'], array_column($this->service->listPartnerCompanies($cfa), 'name'));
    }

    public function test_adding_an_unverified_company_is_rejected(): void
    {
        $cfa = $this->employeurCfa();
        $company = Company::factory()->create(); // PENDING par defaut

        $this->assertCode('COMPANY_NOT_VERIFIED', fn () => $this->service->addPartnerCompany($cfa, $company->id));
        $this->assertCount(0, $this->service->listPartnerCompanies($cfa));
    }

    public function test_a_cfa_can_remove_a_partner_company(): void
    {
        $cfa = $this->employeurCfa();
        $company = Company::factory()->verified()->create();
        $this->service->addPartnerCompany($cfa, $company->id);

        $this->service->removePartnerCompany($cfa, $company->id);

        $this->assertCount(0, $this->service->listPartnerCompanies($cfa));
    }

    public function test_search_only_returns_verified_companies(): void
    {
        Company::factory()->verified()->create(['name' => 'Boulangerie Dupont']);
        Company::factory()->create(['name' => 'Boulangerie Martin']); // PENDING

        $resultats = $this->service->searchVerifiedCompanies('Boulangerie');

        $this->assertSame(['Boulangerie Dupont'], array_column($resultats, 'name'));
    }

    // --- Mes candidats ---

    public function test_my_candidates_only_returns_candidates_linked_to_this_cfa(): void
    {
        $cfa = $this->employeurCfa();
        $lesSiens = $this->candidat(['first_name' => 'Yanis']);
        $this->rattacherAuCfa($lesSiens, $cfa);
        $this->candidat(['first_name' => 'Inès']); // pas rattache

        $noms = array_column($this->service->listMyCandidates($cfa), 'first_name');

        $this->assertSame(['Yanis'], $noms);
    }

    // --- Offres d'un partenaire ---

    public function test_partner_offers_lists_only_published_offers_of_that_partner(): void
    {
        $cfa = $this->employeurCfa();
        $partenaire = $this->employeur();
        $this->service->addPartnerCompany($cfa, $partenaire->company->id);

        $offrePubliee = $this->offrePubliee($partenaire, ['title' => 'Vendeur·se en alternance']);
        JobOffer::factory()->create(['company_id' => $partenaire->company->id]); // brouillon

        $titres = array_column($this->service->listPartnerOffers($cfa, $partenaire->company->id), 'title');

        $this->assertSame(['Vendeur·se en alternance'], $titres);
    }

    public function test_partner_offers_rejects_a_company_that_is_not_a_partner(): void
    {
        $cfa = $this->employeurCfa();
        $etrangere = $this->employeur();

        $this->assertCode(
            'COMPANY_NOT_PARTNER',
            fn () => $this->service->listPartnerOffers($cfa, $etrangere->company->id),
        );
    }

    // --- Recommandation ---

    public function test_recommend_creates_a_notification_for_the_employer_and_is_idempotent(): void
    {
        $cfa = $this->employeurCfa(['name' => 'IDA']);
        $employeur = $this->employeur();
        $this->service->addPartnerCompany($cfa, $employeur->company->id);
        $offre = $this->offrePubliee($employeur, ['title' => 'Apprenti·e boulanger·e']);

        $candidatUser = $this->candidat();
        $candidat = $this->profilDe($candidatUser);
        $this->rattacherAuCfa($candidatUser, $cfa);

        $this->service->recommend($cfa, $candidat->id, $offre->id);
        // Un second clic sur le meme couple ne doit pas spammer une seconde
        // notification.
        $this->service->recommend($cfa, $candidat->id, $offre->id);

        $this->assertCount(1, CandidateRecommendation::all());
        $notifications = Notification::where('user_id', $employeur->id)->get();
        $this->assertCount(1, $notifications);
        $this->assertSame(NotificationType::CANDIDATE_RECOMMENDED, $notifications->first()->type);
        $this->assertStringContainsString('IDA', $notifications->first()->message);
    }

    public function test_recommend_rejects_a_candidate_not_linked_to_this_cfa(): void
    {
        $cfa = $this->employeurCfa();
        $employeur = $this->employeur();
        $this->service->addPartnerCompany($cfa, $employeur->company->id);
        $offre = $this->offrePubliee($employeur);

        $candidat = $this->profilDe($this->candidat()); // pas rattache

        $this->assertCode('CANDIDATE_NOT_YOURS', fn () => $this->service->recommend($cfa, $candidat->id, $offre->id));
    }

    public function test_recommend_rejects_an_offer_from_a_non_partner_company(): void
    {
        $cfa = $this->employeurCfa();
        $employeur = $this->employeur(); // jamais declare partenaire
        $offre = $this->offrePubliee($employeur);

        $candidatUser = $this->candidat();
        $candidat = $this->profilDe($candidatUser);
        $this->rattacherAuCfa($candidatUser, $cfa);

        $this->assertCode('OFFER_NOT_PARTNER', fn () => $this->service->recommend($cfa, $candidat->id, $offre->id));
    }

    public function test_recommend_rejects_an_ineligible_candidate(): void
    {
        $cfa = $this->employeurCfa();
        $employeur = $this->employeur();
        $this->service->addPartnerCompany($cfa, $employeur->company->id);
        $offre = $this->offrePubliee($employeur); // Perpignan, rayon par defaut

        // A l'autre bout de la France : hors du rayon de l'offre comme de
        // celui du candidat. latitude/longitude ne sont pas mass-assignables
        // (voir CandidateProfile), candidat() les ignorerait silencieusement
        // puis located() les ecraserait sur Perpignan — forceFill apres coup.
        $candidatUser = $this->candidat();
        $candidat = $this->profilDe($candidatUser);
        $candidat->forceFill(['latitude' => 50.63, 'longitude' => 3.06])->saveQuietly();
        $this->rattacherAuCfa($candidatUser, $cfa);

        $this->assertCode(
            'CANDIDATE_NOT_ELIGIBLE',
            fn () => $this->service->recommend($cfa, $candidat->id, $offre->id),
        );
        $this->assertCount(0, CandidateRecommendation::all());
    }

    // --- Reception cote entreprise ---

    public function test_received_recommendations_lists_for_the_right_company_only(): void
    {
        $cfa = $this->employeurCfa();
        $employeur = $this->employeur();
        $autreEmployeur = $this->employeur();
        $this->service->addPartnerCompany($cfa, $employeur->company->id);
        $this->service->addPartnerCompany($cfa, $autreEmployeur->company->id);
        $offre = $this->offrePubliee($employeur);
        $autreOffre = $this->offrePubliee($autreEmployeur);

        $candidatUser = $this->candidat();
        $candidat = $this->profilDe($candidatUser);
        $this->rattacherAuCfa($candidatUser, $cfa);
        $this->service->recommend($cfa, $candidat->id, $offre->id);

        $recues = $this->service->listReceivedRecommendations($employeur);
        $this->assertCount(1, $recues);
        $this->assertSame($offre->id, $recues[0]['job_offer']['id']);

        $this->assertCount(0, $this->service->listReceivedRecommendations($autreEmployeur));
    }

    // --- Acces ---

    public function test_the_recommend_route_is_closed_to_non_cfa_accounts(): void
    {
        $candidatUser = $this->candidat();

        $this->withHeader('Authorization', 'Bearer '.$this->jeton($candidatUser))
            ->postJson('/api/cfa/recommendations', ['candidate_profile_id' => 1, 'job_offer_id' => 1])
            ->assertStatus(403);
    }

    private function rattacherAuCfa(User $candidatUser, User $cfaUser): void
    {
        $candidatUser->candidateProfile()->firstOrFail()
            ->forceFill(['cfa_organization_id' => $cfaUser->cfaOrganization->id])
            ->saveQuietly();
    }

    private function assertCode(string $code, callable $appel): void
    {
        try {
            $appel();
            $this->fail("Attendu : {$code}.");
        } catch (ApiException $e) {
            $this->assertSame($code, $e->errorCode);
        }
    }
}
