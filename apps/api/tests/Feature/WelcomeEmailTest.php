<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Services\AuthService;
use App\Services\MailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

// Email de bienvenue a l'inscription.
//
// Ce qui compte ici : qu'il parte depuis les DEUX chemins d'inscription, qu'il
// ne parte PAS quand il ne s'agit pas d'une inscription, et surtout qu'un
// echec d'envoi ne fasse jamais perdre le compte.
class WelcomeEmailTest extends TestCase
{
    use RefreshDatabase;

    private $mailServiceMock;

    private AuthService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mailServiceMock = Mockery::mock(MailService::class);
        $this->app->instance(MailService::class, $this->mailServiceMock);
        $this->service = $this->app->make(AuthService::class);
    }

    // --- Il part bien ---

    public function test_a_candidate_registration_sends_the_welcome_email(): void
    {
        $this->mailServiceMock->shouldReceive('sendWelcomeEmail')
            ->once()
            ->with('lea@example.com', UserRole::CANDIDATE);

        $this->service->register('lea@example.com', 'Password123!', UserRole::CANDIDATE);
    }

    // Le role est transmis tel quel : c'est lui qui decide du contenu, un
    // candidat et une entreprise n'ayant pas la meme etape suivante.
    public function test_a_company_registration_passes_its_own_role(): void
    {
        $this->mailServiceMock->shouldReceive('sendWelcomeEmail')
            ->once()
            ->with('rh@nexatech.example.com', UserRole::COMPANY);

        $this->service->register('rh@nexatech.example.com', 'Password123!', UserRole::COMPANY);
    }

    public function test_a_cfa_registration_passes_its_own_role(): void
    {
        $this->mailServiceMock->shouldReceive('sendWelcomeEmail')
            ->once()
            ->with('contact@cfa.example.com', UserRole::CFA);

        $this->service->register('contact@cfa.example.com', 'Password123!', UserRole::CFA);
    }

    // --- Il ne part pas quand il ne faut pas ---

    // Un email deja pris n'est pas une inscription : le compte n'est pas cree,
    // rien ne doit partir.
    public function test_a_refused_registration_sends_nothing(): void
    {
        User::create([
            'email' => 'lea@example.com',
            'password_hash' => 'x',
            'role' => UserRole::CANDIDATE,
        ]);

        $this->mailServiceMock->shouldNotReceive('sendWelcomeEmail');

        try {
            $this->service->register('lea@example.com', 'Password123!', UserRole::CANDIDATE);
            $this->fail('Un email deja utilise doit etre refuse.');
        } catch (ApiException) {
            // attendu
        }
    }

    // --- L'inscription prime toujours sur l'email ---

    // Le point le plus important du lot : un compte perdu parce qu'un serveur
    // mail a hoquete serait bien plus grave que l'email manquant.
    public function test_the_account_survives_a_failing_mail_service(): void
    {
        $this->mailServiceMock->shouldReceive('sendWelcomeEmail')
            ->once()
            ->andThrow(new \RuntimeException('Resend indisponible'));

        $result = $this->service->register('lea@example.com', 'Password123!', UserRole::CANDIDATE);

        $this->assertSame('lea@example.com', $result['user']->email);
        $this->assertNotNull($result['tokens']['accessToken']);
        $this->assertDatabaseHas('users', ['email' => 'lea@example.com']);
    }

    // --- Le contenu ---

    // Le vrai MailService, sans cle configuree : il journalise au lieu
    // d'envoyer et ne doit surtout pas lever d'exception.
    public function test_the_real_mail_service_is_silent_without_an_api_key(): void
    {
        config(['services.resend.key' => null]);
        $mailService = new MailService;

        $mailService->sendWelcomeEmail('lea@example.com', UserRole::CANDIDATE);

        $this->assertTrue(true, "Aucune exception levee sans cle d'API.");
    }
}
