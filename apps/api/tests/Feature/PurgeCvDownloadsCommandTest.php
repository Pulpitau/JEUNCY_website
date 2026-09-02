<?php

namespace Tests\Feature;

use App\Enums\CvSource;
use App\Enums\UserRole;
use App\Models\CandidateProfile;
use App\Models\CvDownload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// La politique de confidentialite annonce aux candidats une conservation de
// trois ans du journal de telechargement. Ces tests verifient que l'annonce
// est tenue : sans eux, une regression silencieuse rendrait le texte faux.
class PurgeCvDownloadsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function makeDownload(string $downloadedAt): CvDownload
    {
        static $n = 0;
        $n++;

        $candidateUser = User::create([
            'email' => "candidat{$n}@example.com", 'password_hash' => 'x', 'role' => UserRole::CANDIDATE,
        ]);
        $recruiter = User::create([
            'email' => "rh{$n}@example.com", 'password_hash' => 'x', 'role' => UserRole::COMPANY,
        ]);
        $profile = CandidateProfile::create([
            'user_id' => $candidateUser->id, 'first_name' => 'Lea', 'last_name' => 'Girard',
        ]);

        $download = CvDownload::create([
            'candidate_profile_id' => $profile->id,
            'user_id' => $recruiter->id,
            'source' => CvSource::ON_THE_FLY,
        ]);

        // downloaded_at n'est volontairement pas "fillable" : la date d'acces
        // est imposee par la base (useCurrent) et ne doit jamais pouvoir etre
        // dictee par l'appelant, sinon le journal serait falsifiable. Pour
        // simuler une entree ancienne, on la force donc par une ecriture
        // directe plutot que par le modele.
        CvDownload::where('id', $download->id)->update(['downloaded_at' => $downloadedAt]);

        return $download->fresh();
    }

    public function test_purge_deletes_entries_older_than_three_years(): void
    {
        $old = $this->makeDownload(now()->subYears(3)->subDay()->toDateTimeString());

        $this->artisan('cv-downloads:purge')->assertSuccessful();

        $this->assertNull(CvDownload::find($old->id));
    }

    public function test_purge_keeps_entries_within_the_retention_period(): void
    {
        $recent = $this->makeDownload(now()->subYears(2)->toDateTimeString());

        $this->artisan('cv-downloads:purge')->assertSuccessful();

        $this->assertNotNull(CvDownload::find($recent->id));
    }
}
