<?php

namespace Tests\Feature;

use App\Enums\CvSource;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Exceptions\ApiException;
use App\Models\CandidateProfile;
use App\Models\CvDownload;
use App\Models\Subscription;
use App\Models\User;
use App\Services\CandidateProfileService;
use App\Services\CvthequeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

// Telechargement du CV d'un candidat depuis la CVtheque : ordre de priorite
// des sources, garde d'abonnement, respect du retrait de visibilite, et
// journalisation (exigence RGPD, voir la migration create_cv_downloads_table).
class CvthequeDownloadTest extends TestCase
{
    use RefreshDatabase;

    private CvthequeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->service = $this->app->make(CvthequeService::class);
    }

    private function makeSubscriber(string $email = 'rh@nexatech.example.com'): User
    {
        $user = User::create(['email' => $email, 'password_hash' => 'x', 'role' => UserRole::COMPANY]);

        Subscription::create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::ACTIVE,
            'amount_cents' => 49900,
            'stripe_subscription_id' => 'sub_'.$user->id,
            'stripe_customer_id' => 'cus_'.$user->id,
        ]);

        return $user;
    }

    private function makeCandidate(array $overrides = []): CandidateProfile
    {
        static $n = 0;
        $n++;
        $user = User::create(['email' => "candidat{$n}@example.com", 'password_hash' => 'x', 'role' => UserRole::CANDIDATE]);

        return CandidateProfile::create(array_merge([
            'user_id' => $user->id,
            'first_name' => 'Lea',
            'last_name' => 'Girard',
            'headline' => 'Developpeuse web en alternance',
            'city' => 'Perpignan',
            'bio' => 'Passionnee de React.',
        ], $overrides));
    }

    // Depose un vrai CV via le service candidat, comme le ferait le candidat.
    private function attachUploadedCv(CandidateProfile $profile, string $filename = 'Mon CV Canva.pdf'): void
    {
        $service = $this->app->make(CandidateProfileService::class);
        $service->uploadCv(
            $profile->user,
            UploadedFile::fake()->create($filename, 10, 'application/pdf'),
        );
    }

    // --- Ordre de priorite des sources ---

    public function test_uploaded_cv_wins_over_generated_one(): void
    {
        $candidate = $this->makeCandidate();
        $this->attachUploadedCv($candidate);

        // Un CV genere existe aussi, mais le document choisi par le candidat
        // doit primer.
        Storage::disk('public')->put('generated-cvs/x.pdf', '%PDF-genere');
        $candidate->generatedCvs()->create([
            'file_url' => Storage::disk('public')->url('generated-cvs/x.pdf'),
        ]);

        $result = $this->service->downloadCv($this->makeSubscriber(), $candidate->id);

        $this->assertSame(CvSource::UPLOADED, CvDownload::first()->source);
        $this->assertSame('Mon CV Canva.pdf', $result['filename']);
    }

    public function test_generated_cv_is_served_when_no_uploaded_one(): void
    {
        $candidate = $this->makeCandidate();
        Storage::disk('public')->put('generated-cvs/y.pdf', '%PDF-genere');
        $candidate->generatedCvs()->create([
            'file_url' => Storage::disk('public')->url('generated-cvs/y.pdf'),
        ]);

        $result = $this->service->downloadCv($this->makeSubscriber(), $candidate->id);

        $this->assertSame(CvSource::GENERATED, CvDownload::first()->source);
        $this->assertSame('%PDF-genere', $result['contents']);
    }

    // Le cas majoritaire au demarrage : les profils deja en base n'ont jamais
    // clique sur "Generer mon CV". Sans ce repli, la CVtheque n'aurait aucun
    // CV a proposer pour eux.
    public function test_cv_is_generated_on_the_fly_when_candidate_has_none(): void
    {
        $candidate = $this->makeCandidate();

        $result = $this->service->downloadCv($this->makeSubscriber(), $candidate->id);

        $this->assertSame(CvSource::ON_THE_FLY, CvDownload::first()->source);
        $this->assertStringStartsWith('%PDF', $result['contents']);
        $this->assertSame('CV-lea-girard.pdf', $result['filename']);
    }

    // Un CV genere puis archive a vu son fichier supprime du disque
    // (ArchiveInactiveCvs) : le servir donnerait un PDF vide.
    public function test_archived_generated_cv_falls_back_to_on_the_fly(): void
    {
        $candidate = $this->makeCandidate();
        $candidate->generatedCvs()->create([
            'file_url' => Storage::disk('public')->url('generated-cvs/gone.pdf'),
            'archived_at' => now(),
        ]);

        $this->service->downloadCv($this->makeSubscriber(), $candidate->id);

        $this->assertSame(CvSource::ON_THE_FLY, CvDownload::first()->source);
    }

    // --- Gardes ---

    public function test_download_is_refused_without_active_subscription(): void
    {
        $candidate = $this->makeCandidate();
        $user = User::create(['email' => 'sans-abo@example.com', 'password_hash' => 'x', 'role' => UserRole::COMPANY]);

        $this->expectException(ApiException::class);
        $this->service->downloadCv($user, $candidate->id);
    }

    // Droit d'opposition (RGPD art. 21) : un candidat retire de la CVtheque
    // ne doit plus etre telechargeable, meme par un recruteur qui connaissait
    // deja son identifiant.
    public function test_download_is_refused_when_candidate_left_the_cvtheque(): void
    {
        $candidate = $this->makeCandidate(['is_visible_in_cvtheque' => false]);
        $this->attachUploadedCv($candidate);

        $this->expectException(ApiException::class);
        $this->service->downloadCv($this->makeSubscriber(), $candidate->id);
    }

    public function test_no_download_is_logged_when_access_is_refused(): void
    {
        $candidate = $this->makeCandidate(['is_visible_in_cvtheque' => false]);

        try {
            $this->service->downloadCv($this->makeSubscriber(), $candidate->id);
        } catch (ApiException) {
            // attendu
        }

        $this->assertSame(0, CvDownload::count());
    }

    // --- Journalisation ---

    public function test_each_download_is_logged_with_recruiter_and_candidate(): void
    {
        $candidate = $this->makeCandidate();
        $recruiter = $this->makeSubscriber();

        $this->service->downloadCv($recruiter, $candidate->id);
        $this->service->downloadCv($recruiter, $candidate->id);

        // Deux lignes et non une : c'est la frequence des acces qui revele un
        // usage anormal, un dedoublonnage la masquerait.
        $this->assertSame(2, CvDownload::count());

        $log = CvDownload::first();
        $this->assertSame($recruiter->id, $log->user_id);
        $this->assertSame($candidate->id, $log->candidate_profile_id);
        $this->assertNotNull($log->downloaded_at);
    }

    // --- Fuite d'URL ---

    // Le recruteur ne doit jamais recevoir l'URL publique du fichier : avec
    // elle il contournerait la garde d'abonnement et le journal.
    public function test_detail_never_exposes_the_raw_cv_url(): void
    {
        $candidate = $this->makeCandidate();
        $this->attachUploadedCv($candidate);

        $payload = $this->service->find($this->makeSubscriber(), $candidate->id)->toArray();

        $this->assertArrayNotHasKey('cv_file_url', $payload);
        $this->assertTrue($payload['has_uploaded_cv']);
    }

    public function test_detail_reports_absence_of_uploaded_cv(): void
    {
        $candidate = $this->makeCandidate();

        $payload = $this->service->find($this->makeSubscriber(), $candidate->id)->toArray();

        $this->assertFalse($payload['has_uploaded_cv']);
    }
}
