<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\CvSource;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Exceptions\ApiException;
use App\Models\Application;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\CvDownload;
use App\Models\JobOffer;
use App\Models\Subscription;
use App\Models\User;
use App\Services\CandidateProfileService;
use App\Services\CvthequeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

// Telechargement du CV d'un candidat depuis la CVtheque : ordre de priorite
// des sources, gardes d'acces, respect du retrait de visibilite, et
// journalisation (exigence RGPD, voir la migration create_cv_downloads_table).
//
// Depuis le lot 1 une garde de plus, et c'est la plus structurante : le CV
// n'est servi a un employeur QUE si le candidat a postule a l'une de ses
// offres (CV_NOT_SHARED sinon). D'ou le helper makeApplicationFor() appele
// par tous les tests qui telechargent avec un compte entreprise — sans lui
// ils recevraient tous un 403, et ne prouveraient plus rien sur les sources.
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

        // Fiche entreprise VERIFIED : requireVerified refuse tout le reste.
        Company::factory()->verified()->create(['user_id' => $user->id, 'name' => 'NexaTech']);

        Subscription::create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::ACTIVE,
            'amount_cents' => 49900,
            'stripe_subscription_id' => 'sub_'.$user->id,
            'stripe_customer_id' => 'cus_'.$user->id,
        ]);

        return $user->fresh();
    }

    // Le geste du candidat qui ouvre son CV a cet employeur, et a lui seul.
    private function makeApplicationFor(CandidateProfile $candidate, User $recruiter): Application
    {
        $offer = JobOffer::factory()->published()->create([
            'company_id' => $recruiter->company->id,
        ]);

        return Application::create([
            'candidate_profile_id' => $candidate->id,
            'job_offer_id' => $offer->id,
            'status' => ApplicationStatus::SENT,
        ]);
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

    // --- La garde de partage ---

    public function test_download_refused_without_application(): void
    {
        $candidate = $this->makeCandidate();
        $recruiter = $this->makeSubscriber();

        try {
            $this->service->downloadCv($recruiter, $candidate->id);
            $this->fail('Un employeur sans candidature ne doit pas obtenir le CV.');
        } catch (ApiException $e) {
            $this->assertSame('CV_NOT_SHARED', $e->errorCode);
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->assertSame(0, CvDownload::count());
    }

    public function test_download_allowed_after_application(): void
    {
        $candidate = $this->makeCandidate();
        $recruiter = $this->makeSubscriber();
        $this->makeApplicationFor($candidate, $recruiter);

        $result = $this->service->downloadCv($recruiter, $candidate->id);

        $this->assertStringStartsWith('%PDF', $result['contents']);
    }

    // Une candidature chez un AUTRE employeur n'ouvre rien : le partage vaut
    // pour celui a qui le candidat s'est adresse, pas pour la place entiere.
    public function test_application_to_another_company_does_not_share_the_cv(): void
    {
        $candidate = $this->makeCandidate();
        $autre = $this->makeSubscriber('rh@autre.example.com');
        $this->makeApplicationFor($candidate, $autre);

        $this->expectException(ApiException::class);
        $this->service->downloadCv($this->makeSubscriber(), $candidate->id);
    }

    // Acces interne deja assume par la route (routes/api/cvtheque.php) :
    // l'equipe Jeuncy est responsable de traitement de ces donnees.
    public function test_admin_downloads_without_application(): void
    {
        $candidate = $this->makeCandidate();
        $admin = User::create(['email' => 'admin@jeuncy.com', 'password_hash' => 'x', 'role' => UserRole::ADMIN]);

        $result = $this->service->downloadCv($admin, $candidate->id);

        $this->assertStringStartsWith('%PDF', $result['contents']);
    }

    public function test_staff_downloads_without_application(): void
    {
        $candidate = $this->makeCandidate();
        $staff = User::create(['email' => 'collegue@jeuncy.com', 'password_hash' => 'x', 'role' => UserRole::STAFF]);

        $result = $this->service->downloadCv($staff, $candidate->id);

        $this->assertStringStartsWith('%PDF', $result['contents']);
    }

    // --- Ordre de priorite des sources ---

    public function test_uploaded_cv_wins_over_generated_one(): void
    {
        $candidate = $this->makeCandidate();
        $this->attachUploadedCv($candidate);
        $recruiter = $this->makeSubscriber();
        $this->makeApplicationFor($candidate, $recruiter);

        // Un CV genere existe aussi, mais le document choisi par le candidat
        // doit primer.
        Storage::disk('public')->put('generated-cvs/x.pdf', '%PDF-genere');
        $candidate->generatedCvs()->create([
            'file_url' => Storage::disk('public')->url('generated-cvs/x.pdf'),
        ]);

        $result = $this->service->downloadCv($recruiter, $candidate->id);

        $this->assertSame(CvSource::UPLOADED, CvDownload::first()->source);
        $this->assertSame('Mon CV Canva.pdf', $result['filename']);
    }

    // Un CV genere et stocke n'est deliberement PLUS servi : le recruteur doit
    // voir le profil tel qu'il est aujourd'hui, et un vieux fichier sur le
    // disque masquerait indefiniment toute correction du gabarit — c'est ce qui
    // a fait croire a deux reprises qu'un correctif du CV etait inefficace.
    public function test_stored_generated_cv_is_never_served(): void
    {
        $candidate = $this->makeCandidate();
        $recruiter = $this->makeSubscriber();
        $this->makeApplicationFor($candidate, $recruiter);

        Storage::disk('public')->put('generated-cvs/y.pdf', '%PDF-perime');
        $candidate->generatedCvs()->create([
            'file_url' => Storage::disk('public')->url('generated-cvs/y.pdf'),
        ]);

        $result = $this->service->downloadCv($recruiter, $candidate->id);

        $this->assertSame(CvSource::ON_THE_FLY, CvDownload::first()->source);
        $this->assertNotSame('%PDF-perime', $result['contents']);
        $this->assertStringStartsWith('%PDF', $result['contents']);
    }

    // Le cas majoritaire au demarrage : les profils deja en base n'ont jamais
    // clique sur "Generer mon CV". Sans ce repli, la CVtheque n'aurait aucun
    // CV a proposer pour eux.
    public function test_cv_is_generated_on_the_fly_when_candidate_has_none(): void
    {
        $candidate = $this->makeCandidate();
        $recruiter = $this->makeSubscriber();
        $this->makeApplicationFor($candidate, $recruiter);

        $result = $this->service->downloadCv($recruiter, $candidate->id);

        $this->assertSame(CvSource::ON_THE_FLY, CvDownload::first()->source);
        $this->assertStringStartsWith('%PDF', $result['contents']);
        $this->assertSame('CV-lea-girard.pdf', $result['filename']);
    }

    // Meme resultat pour un CV archive (fichier supprime du disque par
    // ArchiveInactiveCvs) : le rendu a la demande couvre tous les cas.
    public function test_archived_generated_cv_also_renders_on_the_fly(): void
    {
        $candidate = $this->makeCandidate();
        $recruiter = $this->makeSubscriber();
        $this->makeApplicationFor($candidate, $recruiter);

        $candidate->generatedCvs()->create([
            'file_url' => Storage::disk('public')->url('generated-cvs/gone.pdf'),
            'archived_at' => now(),
        ]);

        $this->service->downloadCv($recruiter, $candidate->id);

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
    // deja son identifiant — et meme s'il a postule chez lui.
    public function test_download_is_refused_when_candidate_left_the_cvtheque(): void
    {
        $candidate = $this->makeCandidate(['is_visible_in_cvtheque' => false]);
        $this->attachUploadedCv($candidate);
        $recruiter = $this->makeSubscriber();
        $this->makeApplicationFor($candidate, $recruiter);

        $this->expectException(ApiException::class);
        $this->service->downloadCv($recruiter, $candidate->id);
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
        $this->makeApplicationFor($candidate, $recruiter);

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
    // elle il contournerait la garde de partage et le journal.
    public function test_detail_never_exposes_the_raw_cv_url(): void
    {
        $candidate = $this->makeCandidate();
        $this->attachUploadedCv($candidate);
        $recruiter = $this->makeSubscriber();
        $this->makeApplicationFor($candidate, $recruiter);

        $payload = $this->service->find($recruiter, $candidate->id);

        $this->assertArrayNotHasKey('cv_file_url', $payload);
        $this->assertTrue($payload['has_uploaded_cv']);
        $this->assertTrue($payload['cv_available']);
    }

    public function test_detail_reports_absence_of_uploaded_cv(): void
    {
        $candidate = $this->makeCandidate();

        $payload = $this->service->find($this->makeSubscriber(), $candidate->id);

        $this->assertFalse($payload['has_uploaded_cv']);
        // Aucune candidature : le bouton de telechargement ne doit pas
        // s'afficher cote web.
        $this->assertFalse($payload['cv_available']);
    }
}
