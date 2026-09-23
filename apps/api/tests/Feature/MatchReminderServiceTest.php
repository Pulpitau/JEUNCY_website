<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\InterestDecision;
use App\Enums\MatchClosedReason;
use App\Enums\MatchReminderStage;
use App\Enums\NotificationType;
use App\Models\Application;
use App\Models\JobOffer;
use App\Models\Notification;
use App\Models\OfferInterest;
use App\Models\User;
use App\Services\MatchReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AideMatch;
use Tests\TestCase;

/**
 * La cascade de relances (MOBILE.md §5).
 *
 * Deux invariants comptent plus que les delais eux-memes, et sont testes en
 * premier : la commande est IDEMPOTENTE (le cron d'OVH saute et rejoue des
 * passages), et elle ne revele JAMAIS a un employeur qu'un candidat s'est
 * interesse a lui sans reponse de sa part.
 */
class MatchReminderServiceTest extends TestCase
{
    use AideMatch;
    use RefreshDatabase;

    private function service(): MatchReminderService
    {
        return app(MatchReminderService::class);
    }

    /** @return array{0: User, 1: User, 2: JobOffer} */
    private function couple(): array
    {
        $candidat = $this->candidat();
        $employeur = $this->employeur();

        return [$candidat, $employeur, $this->offrePubliee($employeur)];
    }

    private function interet(User $candidat, JobOffer $offre, array $attributs): OfferInterest
    {
        return OfferInterest::create(array_merge([
            'candidate_profile_id' => $candidat->candidateProfile->id,
            'job_offer_id' => $offre->id,
        ], $attributs));
    }

    private function relances(User $user): int
    {
        return Notification::query()
            ->where('user_id', $user->id)
            ->where('type', NotificationType::MATCH_REMINDER->value)
            ->count();
    }

    // -----------------------------------------------------------------
    // Idempotence
    // -----------------------------------------------------------------

    public function test_a_second_pass_sends_nothing_more(): void
    {
        [$candidat, $employeur, $offre] = $this->couple();
        $this->interet($candidat, $offre, [
            'employer_decision' => InterestDecision::LIKE,
            'employer_decided_at' => now()->subDays(4),
        ]);

        $this->service()->run();
        $this->assertSame(1, $this->relances($candidat));

        // Le cron d'OVH repasse dans l'heure : rien ne doit repartir.
        $this->service()->run();
        $this->assertSame(1, $this->relances($candidat));
        $this->assertSame(0, $this->relances($employeur));
    }

    public function test_a_forgotten_line_jumps_straight_to_its_due_stage(): void
    {
        // Trois semaines sans passage (panne de cron) : la ligne doit finir
        // expiree en UNE passe, pas recevoir un rappel de J+3 trois semaines
        // trop tard puis attendre le lendemain.
        [$candidat, , $offre] = $this->couple();
        $ligne = $this->interet($candidat, $offre, [
            'employer_decision' => InterestDecision::LIKE,
            'employer_decided_at' => now()->subDays(21),
        ]);

        $this->service()->run();

        $ligne->refresh();
        $this->assertSame(
            MatchReminderStage::EMPLOYER_INTEREST_EXPIRED->value,
            $ligne->reminder_stage,
        );
        $this->assertNotNull($ligne->closed_at);
        $this->assertSame(MatchClosedReason::EXPIRED, $ligne->closed_reason);
    }

    // -----------------------------------------------------------------
    // Ce que la cascade ne dit jamais
    // -----------------------------------------------------------------

    public function test_a_one_sided_candidate_interest_never_reaches_the_employer(): void
    {
        // Le candidat a dit oui, l'employeur n'a rien vu : il ne doit RIEN
        // recevoir de nominatif. Le lui dire reviendrait a montrer un geste
        // que le produit lui avait cache (§5).
        [$candidat, $employeur, $offre] = $this->couple();
        $this->interet($candidat, $offre, [
            'candidate_decision' => InterestDecision::LIKE,
            'candidate_decided_at' => now()->subDays(8),
        ]);

        $this->service()->run();

        $this->assertSame(0, $this->relances($employeur));
        $this->assertSame(1, $this->relances($candidat));
    }

    public function test_an_unmatched_expiry_is_announced_to_nobody(): void
    {
        // Un interet a sens unique n'avait ete annonce a personne : sa fin
        // non plus. Meme regle que MatchClosingService.
        [$candidat, $employeur, $offre] = $this->couple();
        $this->interet($candidat, $offre, [
            'candidate_decision' => InterestDecision::LIKE,
            'candidate_decided_at' => now()->subDays(20),
        ]);

        $this->service()->run();

        $this->assertSame(0, $this->relances($candidat));
        $this->assertSame(0, $this->relances($employeur));
    }

    // -----------------------------------------------------------------
    // Les quatre cascades
    // -----------------------------------------------------------------

    public function test_employer_interest_reminds_the_candidate_at_three_days(): void
    {
        [$candidat, , $offre] = $this->couple();
        $this->interet($candidat, $offre, [
            'employer_decision' => InterestDecision::LIKE,
            'employer_decided_at' => now()->subDays(3),
        ]);

        $this->service()->run();

        $this->assertSame(1, $this->relances($candidat));
    }

    public function test_nothing_moves_before_the_delay(): void
    {
        [$candidat, , $offre] = $this->couple();
        $ligne = $this->interet($candidat, $offre, [
            'employer_decision' => InterestDecision::LIKE,
            'employer_decided_at' => now()->subDay(),
        ]);

        $this->service()->run();

        $this->assertSame(0, $this->relances($candidat));
        $this->assertNull($ligne->fresh()->reminder_stage);
    }

    public function test_a_match_without_application_reminds_the_candidate_twice(): void
    {
        [$candidat, , $offre] = $this->couple();
        $ligne = $this->interet($candidat, $offre, [
            'candidate_decision' => InterestDecision::LIKE,
            'employer_decision' => InterestDecision::LIKE,
            'candidate_decided_at' => now()->subDays(3),
            'employer_decided_at' => now()->subDays(3),
            'matched_at' => now()->subDays(2),
        ]);

        $this->service()->run();
        $this->assertSame(1, $this->relances($candidat));
        $this->assertSame(
            MatchReminderStage::MATCH_NO_APPLICATION_D2->value,
            $ligne->fresh()->reminder_stage,
        );

        // Cinq jours plus tard, le second rappel part.
        $ligne->matched_at = now()->subDays(7);
        $ligne->saveQuietly();
        $this->service()->run();

        $this->assertSame(2, $this->relances($candidat));
    }

    public function test_a_silent_employer_is_reminded_then_the_case_is_closed(): void
    {
        [$candidat, $employeur, $offre] = $this->couple();
        $dossier = Application::create([
            'candidate_profile_id' => $candidat->candidateProfile->id,
            'job_offer_id' => $offre->id,
            'status' => ApplicationStatus::SENT,
        ]);
        $dossier->created_at = now()->subDays(3);
        $dossier->saveQuietly();

        $this->service()->run();
        // J+3 : c'est l'EMPLOYEUR qu'on relance, pas le candidat.
        $this->assertSame(1, $this->relances($employeur));
        $this->assertSame(0, $this->relances($candidat));

        // J+30 : Jeuncy cloture, et le dit au candidat.
        $dossier->created_at = now()->subDays(30);
        $dossier->saveQuietly();
        $this->service()->run();

        $this->assertSame(1, $this->relances($candidat));
        $this->assertSame(
            MatchReminderStage::APPLICATION_SILENT_CLOSED->value,
            $dossier->fresh()->reminder_stage,
        );
    }

    public function test_closing_never_posts_a_status_in_the_employers_name(): void
    {
        // Jeuncy peut dire « on n'a pas eu de reponse ». Il ne peut pas
        // ecrire « refusee » dans l'historique du candidat a la place d'une
        // entreprise qui n'a jamais rien dit.
        [$candidat, , $offre] = $this->couple();
        $dossier = Application::create([
            'candidate_profile_id' => $candidat->candidateProfile->id,
            'job_offer_id' => $offre->id,
            'status' => ApplicationStatus::SENT,
        ]);
        $dossier->created_at = now()->subDays(31);
        $dossier->saveQuietly();

        $this->service()->run();

        $this->assertSame(ApplicationStatus::SENT, $dossier->fresh()->status);
    }

    public function test_an_answered_application_leaves_the_cascade(): void
    {
        [$candidat, $employeur, $offre] = $this->couple();
        $dossier = Application::create([
            'candidate_profile_id' => $candidat->candidateProfile->id,
            'job_offer_id' => $offre->id,
            'status' => ApplicationStatus::SEEN,
        ]);
        $dossier->created_at = now()->subDays(20);
        $dossier->responded_at = now()->subDays(19);
        $dossier->saveQuietly();

        $this->service()->run();

        $this->assertSame(0, $this->relances($employeur));
        $this->assertSame(0, $this->relances($candidat));
    }

    /**
     * LE PIEGE DE responded_at, trouve en mesurant la base de dev le
     * 2026-09-23 : la colonne a ete ajoutee au lot 1, elle est donc NULL sur
     * toute candidature anterieure — y compris celles que l'employeur avait
     * repondues depuis longtemps. Sans ce garde-fou, la premiere passe reelle
     * aurait ecrit « l'entreprise n'a jamais repondu, on cloture » a des
     * candidats dont le dossier etait accepte.
     */
    public function test_an_old_answered_application_without_timestamp_is_ignored(): void
    {
        [$candidat, $employeur, $offre] = $this->couple();

        $dossier = Application::create([
            'candidate_profile_id' => $candidat->candidateProfile->id,
            'job_offer_id' => $offre->id,
            'status' => ApplicationStatus::ACCEPTED,
        ]);
        $dossier->created_at = now()->subDays(90);
        $dossier->responded_at = null;
        $dossier->saveQuietly();

        $this->service()->run();

        $this->assertSame(0, $this->relances($candidat));
        $this->assertSame(0, $this->relances($employeur));
        $this->assertNull($dossier->fresh()->reminder_stage);
    }

    public function test_a_dry_run_counts_without_sending_or_writing(): void
    {
        [$candidat, , $offre] = $this->couple();
        $ligne = $this->interet($candidat, $offre, [
            'employer_decision' => InterestDecision::LIKE,
            'employer_decided_at' => now()->subDays(4),
        ]);

        $compte = $this->service()->run(aBlanc: true);

        $this->assertSame(1, $compte[MatchReminderStage::EMPLOYER_INTEREST_D3->value]);
        // Rien n'est parti, rien n'est ecrit : la passe reelle fera encore
        // exactement le meme travail.
        $this->assertSame(0, $this->relances($candidat));
        $this->assertNull($ligne->fresh()->reminder_stage);
    }

    public function test_a_closed_line_is_left_alone(): void
    {
        [$candidat, , $offre] = $this->couple();
        $this->interet($candidat, $offre, [
            'employer_decision' => InterestDecision::LIKE,
            'employer_decided_at' => now()->subDays(10),
            'closed_at' => now()->subDay(),
            'closed_reason' => MatchClosedReason::OFFER_ARCHIVED,
        ]);

        $this->service()->run();

        $this->assertSame(0, $this->relances($candidat));
    }
}
