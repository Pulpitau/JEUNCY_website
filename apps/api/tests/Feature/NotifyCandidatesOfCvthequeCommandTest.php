<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CandidateProfile;
use App\Models\User;
use App\Services\MailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class NotifyCandidatesOfCvthequeCommandTest extends TestCase
{
    use RefreshDatabase;

    private const LAUNCH = '2026-08-17 00:00:00';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.cvtheque.launched_at' => self::LAUNCH]);
    }

    private function makeCandidate(string $createdAt, ?string $notifiedAt = null): CandidateProfile
    {
        static $n = 0;
        $n++;
        $user = User::create(['email' => "candidat{$n}@example.com", 'password_hash' => 'x', 'role' => UserRole::CANDIDATE]);

        $profile = CandidateProfile::create([
            'user_id' => $user->id,
            'first_name' => 'Lea',
            'last_name' => 'Girard',
            'cvtheque_notified_at' => $notifiedAt,
        ]);

        // created_at force apres coup : Eloquent le remplit automatiquement.
        $profile->forceFill(['created_at' => $createdAt])->save();

        return $profile;
    }

    private function mockMail(int $expectedCalls): void
    {
        $mock = Mockery::mock(MailService::class);
        $mock->shouldReceive('sendCvthequeAnnouncementEmail')->times($expectedCalls);
        $this->app->instance(MailService::class, $mock);
    }

    // Sans --send, la commande ne doit RIEN envoyer : un envoi de masse ne part
    // pas sur une faute de frappe.
    public function test_dry_run_sends_nothing(): void
    {
        $this->makeCandidate('2026-08-01 10:00:00');
        $this->mockMail(0);

        $this->artisan('candidates:notify-cvtheque')->assertSuccessful();

        $this->assertNull(CandidateProfile::first()->cvtheque_notified_at);
    }

    public function test_send_notifies_candidates_registered_before_launch(): void
    {
        $this->makeCandidate('2026-08-01 10:00:00');
        $this->mockMail(1);

        $this->artisan('candidates:notify-cvtheque --send')->assertSuccessful();

        $this->assertNotNull(CandidateProfile::first()->cvtheque_notified_at);
    }

    // Les candidats inscrits APRES l'ouverture ont vu la mention sur le
    // formulaire d'inscription : les relancer par email n'aurait aucun sens.
    public function test_candidates_registered_after_launch_are_not_notified(): void
    {
        $this->makeCandidate('2026-08-20 10:00:00');
        $this->mockMail(0);

        $this->artisan('candidates:notify-cvtheque --send')->assertSuccessful();

        $this->assertNull(CandidateProfile::first()->cvtheque_notified_at);
    }

    // Le point qui compte : relancer la commande ne doit pas renvoyer l'email.
    // Un rappel repete se lirait comme du demarchage.
    public function test_running_twice_does_not_send_again(): void
    {
        $this->makeCandidate('2026-08-01 10:00:00');
        $this->mockMail(1);

        $this->artisan('candidates:notify-cvtheque --send')->assertSuccessful();
        $this->artisan('candidates:notify-cvtheque --send')->assertSuccessful();
    }

    public function test_already_notified_candidate_is_skipped(): void
    {
        $this->makeCandidate('2026-08-01 10:00:00', '2026-08-17 09:00:00');
        $this->mockMail(0);

        $this->artisan('candidates:notify-cvtheque --send')->assertSuccessful();
    }
}
