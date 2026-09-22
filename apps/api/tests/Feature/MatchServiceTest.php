<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\MatchClosedReason;
use App\Enums\VerificationStatus;
use App\Models\JobOffer;
use App\Models\OfferInterest;
use App\Models\User;
use App\Services\BlockService;
use App\Services\InterestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AideMatch;
use Tests\TestCase;

/**
 * La liste des matchs, des deux cotes.
 *
 * L'asymetrie est le sujet : le candidat voit l'offre entiere et l'etat de
 * son dossier ; l'employeur ne voit que la CARTE tant que le dossier n'est
 * pas envoye. Le match ouvre la conversation, il ne livre ni le CV ni les
 * coordonnees.
 */
class MatchServiceTest extends TestCase
{
    use AideMatch;
    use RefreshDatabase;

    private InterestService $interests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->chargerLesRoutesDuMatch();
        $this->ouvrirLePerimetre();
        $this->interests = $this->app->make(InterestService::class);
    }

    private function matcher(User $candidat, User $employeur, JobOffer $offre): OfferInterest
    {
        $this->interests->like($employeur, $offre, $this->profilDe($candidat));
        $this->interests->like($candidat, $offre);

        return OfferInterest::whereNotNull('matched_at')->latest('id')->firstOrFail();
    }

    public function test_list_for_candidate_shows_offer_and_status(): void
    {
        $candidat = $this->candidat();
        $employeur = $this->employeur();
        $offre = $this->offrePubliee($employeur, ['title' => 'Vendeur conseil']);
        $this->matcher($candidat, $employeur, $offre);

        $ligne = $this->withToken($this->jeton($candidat))->getJson('/api/matches')
            ->assertOk()->json('data.0');

        $this->assertSame($offre->id, $ligne['job_offer']['id']);
        $this->assertSame('Vendeur conseil', $ligne['job_offer']['title']);
        $this->assertNull($ligne['application']);
        $this->assertSame('AWAITING_APPLICATION', $ligne['status']);
    }

    public function test_list_for_employer_shows_card_then_full_application(): void
    {
        $candidat = $this->candidat(['phone' => '0600000000']);
        $employeur = $this->employeur();
        $offre = $this->offrePubliee($employeur);
        $this->matcher($candidat, $employeur, $offre);

        $ligne = $this->withToken($this->jeton($employeur))->getJson('/api/matches')
            ->assertOk()->json('data.0');

        $this->assertSame($this->profilDe($candidat)->id, $ligne['candidate']['id']);
        // La carte, pas le dossier : ni nom complet, ni telephone, ni ville.
        foreach (['last_name', 'phone', 'city', 'email', 'birth_date'] as $interdit) {
            $this->assertArrayNotHasKey($interdit, $ligne['candidate']);
        }
        $this->assertNull($ligne['application']);
        $this->assertSame('AWAITING_APPLICATION', $ligne['status']);

        // Le dossier arrive : l'employeur le voit en entier.
        $this->profilDe($candidat)->applications()->create([
            'job_offer_id' => $offre->id,
            'status' => ApplicationStatus::SENT,
            'contact_phone' => '0600000000',
        ]);
        $interest = OfferInterest::firstOrFail();
        $interest->application_id = $this->profilDe($candidat)->applications()->value('id');
        $interest->save();

        $ligne = $this->withToken($this->jeton($employeur))->getJson('/api/matches')->json('data.0');

        $this->assertSame('APPLICATION_SENT', $ligne['status']);
        $this->assertSame('0600000000', $ligne['application']['contact_phone']);
    }

    public function test_closed_matches_are_hidden(): void
    {
        $candidat = $this->candidat();
        $employeur = $this->employeur();
        $offre = $this->offrePubliee($employeur);
        $interest = $this->matcher($candidat, $employeur, $offre);

        $interest->closed_at = now();
        $interest->closed_reason = MatchClosedReason::OFFER_ARCHIVED;
        $interest->save();

        $this->withToken($this->jeton($candidat))->getJson('/api/matches')
            ->assertOk()->assertJsonCount(0, 'data');
        $this->withToken($this->jeton($employeur))->getJson('/api/matches')
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_foreign_match_is_404(): void
    {
        $candidat = $this->candidat();
        $employeur = $this->employeur();
        $offre = $this->offrePubliee($employeur);
        $interest = $this->matcher($candidat, $employeur, $offre);

        $intrus = $this->candidat([], ['email' => 'intrus@example.test']);

        // 404 et non 403 : « interdit » confirmerait que la ligne existe.
        $this->withToken($this->jeton($intrus))
            ->getJson('/api/matches/'.$interest->id)
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'MATCH_NOT_FOUND');
    }

    public function test_show_returns_the_detail_to_the_owner(): void
    {
        $candidat = $this->candidat();
        $employeur = $this->employeur();
        $offre = $this->offrePubliee($employeur);
        $interest = $this->matcher($candidat, $employeur, $offre);

        $this->withToken($this->jeton($candidat))
            ->getJson('/api/matches/'.$interest->id)
            ->assertOk()
            ->assertJsonPath('data.id', $interest->id);
    }

    public function test_an_unverified_company_reads_no_match(): void
    {
        $candidat = $this->candidat();
        $employeur = $this->employeur();
        $offre = $this->offrePubliee($employeur);
        $this->matcher($candidat, $employeur, $offre);

        // Re-verification defavorable apres coup : plus aucune carte.
        $employeur->company->forceFill([
            'verification_status' => VerificationStatus::REJECTED,
        ])->saveQuietly();

        $this->withToken($this->jeton($employeur->fresh()))
            ->getJson('/api/matches')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'COMPANY_NOT_VERIFIED');
    }

    public function test_a_blocked_match_disappears_for_both(): void
    {
        $candidat = $this->candidat();
        $employeur = $this->employeur();
        $offre = $this->offrePubliee($employeur);
        $this->matcher($candidat, $employeur, $offre);

        app(BlockService::class)->block($candidat, $employeur->id);

        $this->withToken($this->jeton($candidat))->getJson('/api/matches')
            ->assertOk()->assertJsonCount(0, 'data');
        $this->withToken($this->jeton($employeur))->getJson('/api/matches')
            ->assertOk()->assertJsonCount(0, 'data');
    }
}
