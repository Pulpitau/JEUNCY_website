<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\MatchReminderStage;
use App\Enums\ReportContext;
use App\Enums\VerificationStatus;
use App\Models\Application;
use App\Models\Company;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AideMatch;
use Tests\TestCase;

/**
 * Les trois files de moderation (MOBILE.md §10, lot 4).
 *
 * Le test qui compte le plus est celui de la verification manuelle : c'est le
 * trou assume du lot 1 — une organisation laissee PENDING par un registre
 * muet y restait jusqu'a sa prochaine modification de fiche, donc peut-etre
 * jamais.
 */
class AdminModerationTest extends TestCase
{
    use AideMatch;
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_pending_reports_come_oldest_first(): void
    {
        $admin = $this->admin();
        $candidat = $this->candidat();

        $vieux = Report::create([
            'reporter_user_id' => $candidat->id,
            'context' => ReportContext::CARD,
            'reason' => 'ancien',
        ]);
        $vieux->created_at = now()->subDays(3);
        $vieux->saveQuietly();

        Report::create([
            'reporter_user_id' => $candidat->id,
            'context' => ReportContext::OFFER,
            'reason' => 'recent',
        ]);

        $reponse = $this->withToken($this->jeton($admin))->getJson('/api/admin/reports');
        $reponse->assertOk();

        // Le plus ancien en tete : c'est celui qui attend depuis le plus
        // longtemps, pas le dernier arrive.
        $this->assertSame('ancien', $reponse->json('data.data.0.reason'));
    }

    public function test_handling_a_report_does_not_suspend_anyone(): void
    {
        $admin = $this->admin();
        $candidat = $this->candidat();
        $vise = $this->candidat([], ['email' => 'vise@example.test']);

        $report = Report::create([
            'reporter_user_id' => $candidat->id,
            'reported_user_id' => $vise->id,
            'context' => ReportContext::CARD,
            'reason' => 'propos',
        ]);

        $this->withToken($this->jeton($admin))
            ->postJson("/api/admin/reports/{$report->id}/handle")
            ->assertOk();

        $this->assertNotNull($report->fresh()->handled_at);
        // Suspendre reste un geste separe et explicite : il coupe l'acces
        // d'une personne.
        $this->assertFalse($vise->fresh()->is_suspended);
    }

    public function test_a_pending_organisation_can_be_verified_by_hand(): void
    {
        $admin = $this->admin();
        $employeur = $this->employeur(verifiee: false);
        $entreprise = $employeur->company;
        $entreprise->verification_status = VerificationStatus::PENDING;
        $entreprise->saveQuietly();

        $this->withToken($this->jeton($admin))
            ->getJson('/api/admin/verifications')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'COMPANY');

        $this->withToken($this->jeton($admin))
            ->postJson("/api/admin/verifications/COMPANY/{$entreprise->id}", [
                'status' => VerificationStatus::VERIFIED->value,
                'note' => 'SIRET vérifié au téléphone, registre en panne.',
            ])
            ->assertOk();

        $entreprise->refresh();
        $this->assertSame(VerificationStatus::VERIFIED, $entreprise->verification_status);
        $this->assertSame($admin->id, $entreprise->verified_by);
        $this->assertStringContainsString('téléphone', (string) $entreprise->verification_note);
    }

    public function test_a_verification_cannot_be_granted_without_a_reason(): void
    {
        // Ce statut ouvre l'acces a des cartes de mineurs : une verification
        // accordee sans raison ecrite est indistinguable d'une erreur.
        $admin = $this->admin();
        $entreprise = Company::factory()->create(['verification_status' => VerificationStatus::PENDING]);

        $this->withToken($this->jeton($admin))
            ->postJson("/api/admin/verifications/COMPANY/{$entreprise->id}", [
                'status' => VerificationStatus::VERIFIED->value,
            ])
            ->assertStatus(400);

        $this->assertSame(
            VerificationStatus::PENDING,
            $entreprise->fresh()->verification_status,
        );
    }

    public function test_pending_is_refused_as_a_decision(): void
    {
        $admin = $this->admin();
        $entreprise = Company::factory()->create(['verification_status' => VerificationStatus::PENDING]);

        $this->withToken($this->jeton($admin))
            ->postJson("/api/admin/verifications/COMPANY/{$entreprise->id}", [
                'status' => VerificationStatus::PENDING->value,
                'note' => 'on verra plus tard',
            ])
            ->assertStatus(400);
    }

    public function test_silent_employers_are_read_from_the_reminder_cascade(): void
    {
        $admin = $this->admin();
        $candidat = $this->candidat();
        $employeur = $this->employeur();
        $offre = $this->offrePubliee($employeur);

        $silencieuse = Application::create([
            'candidate_profile_id' => $candidat->candidateProfile->id,
            'job_offer_id' => $offre->id,
            'status' => ApplicationStatus::SENT,
        ]);
        $silencieuse->reminder_stage = MatchReminderStage::APPLICATION_SILENT_D7->value;
        $silencieuse->saveQuietly();

        // Une candidature relancee a J+3 seulement n'est pas encore
        // « silencieuse » : l'employeur a encore quelques jours.
        $recente = Application::create([
            'candidate_profile_id' => $this->candidat([], ['email' => 'b@example.test'])->candidateProfile->id,
            'job_offer_id' => $offre->id,
            'status' => ApplicationStatus::SENT,
        ]);
        $recente->reminder_stage = MatchReminderStage::APPLICATION_SILENT_D3->value;
        $recente->saveQuietly();

        $reponse = $this->withToken($this->jeton($admin))->getJson('/api/admin/silent-employers');
        $reponse->assertOk();

        $this->assertSame(1, $reponse->json('data.total'));
        $this->assertSame($silencieuse->id, $reponse->json('data.data.0.id'));
    }

    public function test_the_moderation_queues_are_closed_to_a_company(): void
    {
        $this->withToken($this->jeton($this->employeur()))
            ->getJson('/api/admin/reports')
            ->assertForbidden();
    }
}
