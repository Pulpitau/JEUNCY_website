<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\GeneratedCv;
use App\Models\JobOffer;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\AideMatch;
use Tests\TestCase;

/**
 * Le CV d'un dossier ne quitte plus le serveur sous forme d'adresse (audit de
 * securite du 2026-10-01, A1). Une adresse de fichier fonctionne sans compte
 * et pour toujours : remise a un recruteur, elle survivait au blocage, au
 * retrait de la candidature et a la suppression du compte du candidat.
 */
class ApplicationCvAccessTest extends TestCase
{
    use AideMatch;
    use RefreshDatabase;

    private User $employeur;

    private JobOffer $offre;

    private Application $dossier;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->employeur = $this->employeur();
        $this->offre = $this->offrePubliee($this->employeur, ['applications_unlocked_at' => now()]);

        $profil = $this->profilDe($this->candidat(['first_name' => 'Léa', 'last_name' => 'Girard']));
        Storage::disk('public')->put('application-cvs/dossier.pdf', '%PDF-dossier');

        $this->dossier = Application::create([
            'candidate_profile_id' => $profil->id,
            'job_offer_id' => $this->offre->id,
            'status' => ApplicationStatus::SENT,
            'cv_file_url' => Storage::disk('public')->url('application-cvs/dossier.pdf'),
        ]);
    }

    public function test_the_owner_downloads_the_pdf_itself(): void
    {
        $response = $this->withToken($this->jeton($this->employeur))
            ->get('/api/applications/'.$this->dossier->id.'/cv');

        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame('%PDF-dossier', $response->getContent());
        $this->assertStringContainsString('CV-lea-girard.pdf', $response->headers->get('Content-Disposition'));
    }

    public function test_the_list_of_applications_carries_no_file_address(): void
    {
        $cv = GeneratedCv::create([
            'candidate_profile_id' => $this->dossier->candidate_profile_id,
            'file_url' => Storage::disk('public')->url('generated-cvs/genere.pdf'),
        ]);
        $this->dossier->update(['cv_file_url' => null, 'generated_cv_id' => $cv->id]);

        $response = $this->withToken($this->jeton($this->employeur))
            ->getJson('/api/job-offers/'.$this->offre->id.'/applications');

        $response->assertOk()->assertJsonPath('data.0.has_cv', true);
        $json = $response->getContent();
        $this->assertStringNotContainsString('cv_file_url', $json);
        $this->assertStringNotContainsString('file_url', $json);
        $this->assertStringNotContainsString('genere.pdf', $json);
    }

    public function test_the_employer_sees_the_age_never_the_birth_date_nor_the_address(): void
    {
        $this->dossier->candidateProfile->update(['birth_date' => now()->subYears(17)->subDays(3), 'address' => '12 rue des Lilas']);

        $response = $this->withToken($this->jeton($this->employeur))
            ->getJson('/api/job-offers/'.$this->offre->id.'/applications');

        $response->assertOk()->assertJsonPath('data.0.candidate_profile.age', 17);
        $this->assertArrayNotHasKey('birth_date', $response->json('data.0.candidate_profile'));
        $this->assertStringNotContainsString('rue des Lilas', $response->getContent());
    }

    public function test_the_status_update_response_carries_no_file_address(): void
    {
        $response = $this->withToken($this->jeton($this->employeur))
            ->patchJson('/api/applications/'.$this->dossier->id.'/status', ['status' => 'SEEN']);

        $response->assertOk();
        $this->assertStringNotContainsString('dossier.pdf', $response->getContent());
    }

    public function test_another_employer_cannot_download_it(): void
    {
        $autre = $this->employeur();

        $this->withToken($this->jeton($autre))
            ->getJson('/api/applications/'.$this->dossier->id.'/cv')
            ->assertStatus(403);
    }

    public function test_a_blocked_candidate_cv_is_no_longer_served(): void
    {
        UserBlock::create([
            'blocker_user_id' => $this->dossier->candidateProfile->user_id,
            'blocked_user_id' => $this->employeur->id,
        ]);

        $this->withToken($this->jeton($this->employeur))
            ->getJson('/api/applications/'.$this->dossier->id.'/cv')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'CV_NOT_FOUND');
    }

    public function test_an_archived_generated_cv_is_reported_as_absent(): void
    {
        $cv = GeneratedCv::create([
            'candidate_profile_id' => $this->dossier->candidate_profile_id,
            'file_url' => Storage::disk('public')->url('generated-cvs/archive.pdf'),
            'archived_at' => now(),
        ]);
        $this->dossier->update(['cv_file_url' => null, 'generated_cv_id' => $cv->id]);

        $this->withToken($this->jeton($this->employeur))
            ->getJson('/api/job-offers/'.$this->offre->id.'/applications')
            ->assertJsonPath('data.0.has_cv', false);

        $this->withToken($this->jeton($this->employeur))
            ->getJson('/api/applications/'.$this->dossier->id.'/cv')
            ->assertStatus(404);
    }

    public function test_a_candidate_cannot_use_the_route(): void
    {
        $candidat = $this->dossier->candidateProfile->user;

        $this->withToken($this->jeton($candidat))
            ->getJson('/api/applications/'.$this->dossier->id.'/cv')
            ->assertStatus(403);
    }
}
