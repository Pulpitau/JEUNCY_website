<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\GeneratedCv;
use App\Models\User;
use App\Services\CvService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArchiveInactiveCvsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function makeCandidateWithCv(?Carbon $lastLoginAt): GeneratedCv
    {
        $user = User::create([
            'email' => uniqid().'@example.com',
            'password_hash' => 'x',
            'role' => UserRole::CANDIDATE,
            'last_login_at' => $lastLoginAt,
        ]);
        $profile = $user->candidateProfile()->create([
            'first_name' => 'Test',
            'last_name' => 'User',
        ]);

        $path = "cvs/{$profile->id}/test.pdf";
        Storage::disk('public')->put($path, 'contenu-pdf-factice');

        return $profile->generatedCvs()->create([
            'file_url' => Storage::disk('public')->url($path),
        ]);
    }

    public function test_archives_cv_of_inactive_candidate(): void
    {
        Storage::fake('public');
        $cv = $this->makeCandidateWithCv(now()->subDays(20));

        $this->artisan('cvs:archive-inactive')->assertSuccessful();

        $this->assertNotNull($cv->fresh()->archived_at);
    }

    public function test_deletes_the_stored_file_when_archiving(): void
    {
        Storage::fake('public');
        $cv = $this->makeCandidateWithCv(now()->subDays(20));
        $relativePath = "cvs/{$cv->candidate_profile_id}/test.pdf";
        $this->assertTrue(Storage::disk('public')->exists($relativePath));

        $this->artisan('cvs:archive-inactive');

        $this->assertFalse(Storage::disk('public')->exists($relativePath));
    }

    public function test_does_not_archive_cv_of_active_candidate(): void
    {
        Storage::fake('public');
        $cv = $this->makeCandidateWithCv(now()->subDays(3));

        $this->artisan('cvs:archive-inactive');

        $this->assertNull($cv->fresh()->archived_at);
    }

    public function test_treats_never_logged_in_as_inactive(): void
    {
        Storage::fake('public');
        $cv = $this->makeCandidateWithCv(null);

        $this->artisan('cvs:archive-inactive');

        $this->assertNotNull($cv->fresh()->archived_at);
    }

    public function test_ignores_already_archived_cv(): void
    {
        Storage::fake('public');
        $cv = $this->makeCandidateWithCv(now()->subDays(20));
        app(CvService::class)->archive($cv);
        $archivedAt = $cv->fresh()->archived_at;

        $this->artisan('cvs:archive-inactive');

        $this->assertEquals($archivedAt, $cv->fresh()->archived_at);
    }
}
