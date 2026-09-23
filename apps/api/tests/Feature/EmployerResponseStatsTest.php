<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\JobOffer;
use App\Services\EmployerResponseStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AideMatch;
use Tests\TestCase;

/**
 * Badge « Repond en N jours » (MOBILE.md §5).
 *
 * Le seuil de cinq candidatures est le point : un chiffre tire d'un seul cas
 * serait faux dans les deux sens, et c'est precisement le genre de chiffre
 * qu'un candidat croirait.
 */
class EmployerResponseStatsTest extends TestCase
{
    use AideMatch;
    use RefreshDatabase;

    private function dossierRepondu(JobOffer $offre, int $joursDeDelai, string $email): void
    {
        $candidat = $this->candidat([], ['email' => $email]);

        $dossier = Application::create([
            'candidate_profile_id' => $candidat->candidateProfile->id,
            'job_offer_id' => $offre->id,
            'status' => ApplicationStatus::SEEN,
        ]);
        $dossier->created_at = now()->subDays($joursDeDelai + 1);
        $dossier->responded_at = now()->subDay();
        $dossier->saveQuietly();
    }

    public function test_below_five_answers_nothing_is_claimed(): void
    {
        $employeur = $this->employeur();
        $offre = $this->offrePubliee($employeur);

        foreach ([1, 2, 1, 2] as $i => $jours) {
            $this->dossierRepondu($offre, $jours, "c{$i}@example.test");
        }

        $medianes = app(EmployerResponseStats::class)
            ->medianesPour([$employeur->company->id], []);

        $this->assertSame([], $medianes['COMPANY']);
    }

    public function test_the_median_ignores_a_single_very_late_answer(): void
    {
        // Quatre reponses en deux jours et une en six mois : la moyenne
        // annoncerait des semaines, la mediane dit deux jours — ce qu'un
        // candidat peut raisonnablement attendre.
        $employeur = $this->employeur();
        $offre = $this->offrePubliee($employeur);

        foreach ([2, 2, 2, 2, 180] as $i => $jours) {
            $this->dossierRepondu($offre, $jours, "d{$i}@example.test");
        }

        $medianes = app(EmployerResponseStats::class)
            ->medianesPour([$employeur->company->id], []);

        $this->assertSame(2, $medianes['COMPANY'][$employeur->company->id]);
    }

    public function test_an_unanswered_application_is_not_counted(): void
    {
        $employeur = $this->employeur();
        $offre = $this->offrePubliee($employeur);

        foreach ([1, 1, 1, 1, 1] as $i => $jours) {
            $this->dossierRepondu($offre, $jours, "e{$i}@example.test");
        }

        // Une sixieme, jamais repondue : elle ne doit ni compter ni fausser
        // le delai. Sinon un employeur pourrait ameliorer son badge en
        // ignorant les candidatures difficiles.
        $muette = $this->candidat([], ['email' => 'muette@example.test']);
        Application::create([
            'candidate_profile_id' => $muette->candidateProfile->id,
            'job_offer_id' => $offre->id,
            'status' => ApplicationStatus::SENT,
        ]);

        $medianes = app(EmployerResponseStats::class)
            ->medianesPour([$employeur->company->id], []);

        $this->assertSame(1, $medianes['COMPANY'][$employeur->company->id]);
    }

    public function test_the_deck_carries_the_badge(): void
    {
        $employeur = $this->employeur();
        $offre = $this->offrePubliee($employeur);

        foreach ([3, 3, 3, 3, 3] as $i => $jours) {
            $this->dossierRepondu($offre, $jours, "f{$i}@example.test");
        }

        $this->chargerLesRoutesDuMatch();
        $this->ouvrirLePerimetre();

        $lecteur = $this->candidat([], ['email' => 'lecteur@example.test']);
        $reponse = $this->withToken($this->jeton($lecteur))->getJson('/api/discover/offers');

        $reponse->assertOk()->assertJsonPath('data.jeuncy.0.employer_response_days', 3);
    }

    public function test_an_unknown_employer_carries_no_number(): void
    {
        $this->chargerLesRoutesDuMatch();
        $this->ouvrirLePerimetre();

        $this->offrePubliee($this->employeur());
        $candidat = $this->candidat();

        $this->withToken($this->jeton($candidat))
            ->getJson('/api/discover/offers')
            ->assertOk()
            ->assertJsonPath('data.jeuncy.0.employer_response_days', null);
    }
}
