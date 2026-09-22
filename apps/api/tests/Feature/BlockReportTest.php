<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\ReportContext;
use App\Models\JobOffer;
use App\Models\OfferInterest;
use App\Models\Report;
use App\Models\UserBlock;
use App\Services\ApplicationService;
use App\Services\BlockService;
use App\Services\InterestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AideMatch;
use Tests\TestCase;

/**
 * Blocages et signalements (MOBILE.md §7).
 *
 * POINT DE CONCEPTION VERIFIE ICI : la cible se designe par
 * candidate_profile_id ou job_offer_id, JAMAIS par un user_id. Ni la carte
 * candidat (liste blanche du presenteur) ni la fiche d'une organisation
 * n'exposent l'identifiant du compte d'en face — un client oblige de le
 * fournir devrait l'inventer.
 */
class BlockReportTest extends TestCase
{
    use AideMatch;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->chargerLesRoutesDuMatch();
        $this->ouvrirLePerimetre();
    }

    public function test_block_by_candidate_profile_and_by_offer(): void
    {
        $candidat = $this->candidat();
        $employeur = $this->employeur();
        $offre = $this->offrePubliee($employeur);

        // L'employeur bloque depuis une carte.
        $this->withToken($this->jeton($employeur))
            ->postJson('/api/blocks', ['candidate_profile_id' => $this->profilDe($candidat)->id])
            ->assertCreated()
            ->assertJsonPath('data.blocked_user_id', $candidat->id);

        // Le candidat bloque depuis une offre.
        $autreCandidat = $this->candidat([], ['email' => 'autre@example.test']);
        $this->withToken($this->jeton($autreCandidat))
            ->postJson('/api/blocks', ['job_offer_id' => $offre->id])
            ->assertCreated()
            ->assertJsonPath('data.blocked_user_id', $employeur->id);

        $this->assertSame(2, UserBlock::count());
    }

    public function test_a_client_cannot_block_by_user_id(): void
    {
        $candidat = $this->candidat();
        $employeur = $this->employeur();

        $this->withToken($this->jeton($candidat))
            ->postJson('/api/blocks', ['user_id' => $employeur->id])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'INVALID_INPUT');
    }

    public function test_cannot_block_self(): void
    {
        $candidat = $this->candidat();

        $this->withToken($this->jeton($candidat))
            ->postJson('/api/blocks', ['candidate_profile_id' => $this->profilDe($candidat)->id])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'CANNOT_BLOCK_SELF');
    }

    public function test_an_unknown_target_is_404(): void
    {
        $employeur = $this->employeur();
        // Une offre sans proprietaire resoluble : rien a bloquer.
        $orpheline = JobOffer::factory()->published()->located()->create([
            'company_id' => null,
            'cfa_organization_id' => null,
        ]);

        $this->withToken($this->jeton($employeur))
            ->postJson('/api/blocks', ['job_offer_id' => $orpheline->id])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'BLOCK_TARGET_NOT_FOUND');
    }

    public function test_unblock_requires_ownership(): void
    {
        $candidat = $this->candidat();
        $employeur = $this->employeur();
        $intrus = $this->candidat([], ['email' => 'intrus@example.test']);

        $blocage = app(BlockService::class)->block($candidat, $employeur->id);

        $this->withToken($this->jeton($intrus))
            ->deleteJson('/api/blocks/'.$blocage->id)
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->withToken($this->jeton($candidat))
            ->deleteJson('/api/blocks/'.$blocage->id)
            ->assertOk();

        $this->assertSame(0, UserBlock::count());
    }

    public function test_block_hides_in_both_decks_cvtheque_and_applications(): void
    {
        $candidat = $this->candidat();
        $employeur = $this->employeur();
        $offre = $this->offrePubliee($employeur);

        // Un dossier existe deja : il doit disparaitre de la liste recue.
        $this->app->make(ApplicationService::class)
            ->applyForUser($candidat, $offre, null, '0600000000');

        $this->assertCount(
            1,
            $this->app->make(ApplicationService::class)->listForOffer($employeur, $offre),
        );

        app(BlockService::class)->block($candidat, $employeur->id);

        // Deck employeur.
        $this->assertSame([], array_column(
            $this->withToken($this->jeton($employeur))
                ->getJson('/api/discover/candidates?job_offer_id='.$offre->id)
                ->assertOk()->json('data.data'),
            'id',
        ));

        // Deck candidat.
        $this->assertSame([], $this->withToken($this->jeton($candidat))
            ->getJson('/api/discover/offers')->assertOk()->json('data.jeuncy'));

        // Candidatures recues.
        $this->assertCount(
            0,
            $this->app->make(ApplicationService::class)->listForOffer($employeur->fresh(), $offre),
        );
    }

    public function test_offer_report_targets_owner(): void
    {
        $candidat = $this->candidat();
        $employeur = $this->employeur();
        $offre = $this->offrePubliee($employeur);

        $this->withToken($this->jeton($candidat))
            ->postJson('/api/reports', [
                'context' => ReportContext::OFFER->value,
                'reason' => 'offre_trompeuse',
                'job_offer_id' => $offre->id,
            ])
            ->assertCreated();

        $signalement = Report::firstOrFail();
        $this->assertSame($employeur->id, $signalement->reported_user_id);
        $this->assertSame($offre->id, $signalement->job_offer_id);
        $this->assertSame($candidat->id, $signalement->reporter_user_id);
    }

    public function test_card_report_targets_the_candidate(): void
    {
        $candidat = $this->candidat();
        $employeur = $this->employeur();

        $this->withToken($this->jeton($employeur))
            ->postJson('/api/reports', [
                'context' => ReportContext::CARD->value,
                'reason' => 'propos_inappropries',
                'details' => 'Texte libre problematique.',
                'candidate_profile_id' => $this->profilDe($candidat)->id,
            ])
            ->assertCreated();

        $this->assertSame($candidat->id, Report::firstOrFail()->reported_user_id);
    }

    public function test_a_match_is_reported_by_its_own_line(): void
    {
        $candidat = $this->candidat();
        $employeur = $this->employeur();
        $offre = $this->offrePubliee($employeur);

        $interests = $this->app->make(InterestService::class);
        $interests->like($employeur, $offre, $this->profilDe($candidat));
        $interests->like($candidat, $offre);
        $ligne = OfferInterest::firstOrFail();

        // Le candidat signale : la cible est l'employeur, deduite du role.
        $this->withToken($this->jeton($candidat))
            ->postJson('/api/reports', [
                'context' => ReportContext::MATCH->value,
                'reason' => 'comportement',
                'offer_interest_id' => $ligne->id,
            ])
            ->assertCreated();

        $this->assertSame($employeur->id, Report::firstOrFail()->reported_user_id);
    }

    public function test_a_foreign_match_cannot_be_reported(): void
    {
        $candidat = $this->candidat();
        $employeur = $this->employeur();
        $offre = $this->offrePubliee($employeur);

        $interests = $this->app->make(InterestService::class);
        $interests->like($employeur, $offre, $this->profilDe($candidat));
        $interests->like($candidat, $offre);
        $ligne = OfferInterest::firstOrFail();

        $intrus = $this->candidat([], ['email' => 'intrus@example.test']);

        $this->withToken($this->jeton($intrus))
            ->postJson('/api/reports', [
                'context' => ReportContext::MATCH->value,
                'reason' => 'comportement',
                'offer_interest_id' => $ligne->id,
            ])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'REPORT_TARGET_NOT_FOUND');
    }

    // Symetrique de CANNOT_BLOCK_SELF : un signalement de soi-meme n'a rien a
    // apprendre a l'equipe et encombrerait la file de moderation.
    public function test_cannot_report_self(): void
    {
        $candidat = $this->candidat();
        $profil = $this->profilDe($candidat);

        $this->withToken($this->jeton($candidat))
            ->postJson('/api/reports', [
                'context' => ReportContext::CARD->value,
                'reason' => 'test',
                'candidate_profile_id' => $profil->id,
            ])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'CANNOT_REPORT_SELF');
    }

    public function test_report_deduplicated_24h(): void
    {
        $candidat = $this->candidat();
        $employeur = $this->employeur();
        $corps = [
            'context' => ReportContext::CARD->value,
            'reason' => 'propos_inappropries',
            'candidate_profile_id' => $this->profilDe($candidat)->id,
        ];

        $this->withToken($this->jeton($employeur))->postJson('/api/reports', $corps)->assertCreated();
        $this->withToken($this->jeton($employeur))->postJson('/api/reports', $corps)
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'REPORT_ALREADY_SENT');

        $this->assertSame(1, Report::count());

        // Passe la fenetre : un nouveau signalement est de nouveau recevable.
        Report::query()->update(['created_at' => now()->subHours(25)]);
        $this->withToken($this->jeton($employeur))->postJson('/api/reports', $corps)->assertCreated();

        $this->assertSame(2, Report::count());
    }

    public function test_a_report_survives_the_deletion_of_its_target(): void
    {
        $candidat = $this->candidat();
        $employeur = $this->employeur();

        $this->withToken($this->jeton($employeur))
            ->postJson('/api/reports', [
                'context' => ReportContext::CARD->value,
                'reason' => 'propos_inappropries',
                'candidate_profile_id' => $this->profilDe($candidat)->id,
            ])
            ->assertCreated();

        $candidat->delete();

        // nullOnDelete : le signalement reste lisible par l'equipe.
        $this->assertSame(1, Report::count());
        $this->assertNull(Report::firstOrFail()->reported_user_id);
    }

    public function test_an_application_status_change_keeps_working_after_a_block(): void
    {
        // Un blocage masque le candidat, il ne casse pas les traitements en
        // cours cote employeur.
        $candidat = $this->candidat();
        $employeur = $this->employeur();
        $offre = $this->offrePubliee($employeur);

        $candidature = $this->app->make(ApplicationService::class)
            ->applyForUser($candidat, $offre, null, '0600000000');

        app(BlockService::class)->block($employeur, $candidat->id);

        $this->app->make(ApplicationService::class)
            ->updateStatus($employeur->fresh(), $candidature, ApplicationStatus::SEEN);

        $this->assertNotNull($candidature->fresh()->responded_at);
    }
}
