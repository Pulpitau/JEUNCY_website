<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Services\AuthService;
use App\Services\MailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

/**
 * Aucun CFA ne s'inscrit seul (decision du 2026-09-15).
 *
 * Jeuncy travaille avec une ecole partenaire ; un autre CFA sur la
 * plateforme aurait acces aux memes candidats. La garde porte sur la
 * CREATION de compte, par formulaire comme par Google — jamais sur la
 * connexion d'un CFA existant, sinon l'ecole partenaire elle-meme serait
 * mise a la porte.
 */
class InscriptionCfaFermeeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.jeuncy.inscription_cfa_ouverte', false);

        $mail = Mockery::mock(MailService::class);
        $mail->shouldReceive('sendWelcomeEmail')->andReturnNull();
        $this->app->instance(MailService::class, $mail);
    }

    public function test_a_cfa_cannot_register_through_the_form(): void
    {
        $this->postJson('/api/auth/register', [
            'email' => 'contact@ecole-concurrente.example.com',
            'password' => 'motdepasse',
            'role' => 'CFA',
        ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'CFA_REGISTRATION_CLOSED');

        $this->assertDatabaseMissing('users', ['email' => 'contact@ecole-concurrente.example.com']);
    }

    public function test_the_refusal_tells_where_to_write(): void
    {
        Config::set('services.contact.email', 'bonjour@jeuncy.com');

        $response = $this->postJson('/api/auth/register', [
            'email' => 'contact@ecole.example.com',
            'password' => 'motdepasse',
            'role' => 'CFA',
        ]);

        $this->assertStringContainsString('bonjour@jeuncy.com', $response->json('error.message'));
    }

    public function test_companies_and_candidates_still_register(): void
    {
        $this->postJson('/api/auth/register', ['email' => 'rh@nexatech.example.com', 'password' => 'motdepasse', 'role' => 'COMPANY'])
            ->assertStatus(201);
        $this->postJson('/api/auth/register', ['email' => 'lea@example.com', 'password' => 'motdepasse', 'role' => 'CANDIDATE', 'age_confirmed' => true])
            ->assertStatus(201);
    }

    public function test_an_existing_cfa_still_logs_in(): void
    {
        User::create(['email' => 'contact@ida.example.com', 'password_hash' => 'motdepasse', 'role' => UserRole::CFA]);

        $this->postJson('/api/auth/login', ['email' => 'contact@ida.example.com', 'password' => 'motdepasse'])
            ->assertOk()
            ->assertJsonPath('data.user.role', 'CFA');
    }

    public function test_a_new_cfa_cannot_come_in_through_google(): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage("L'espace CFA n'est pas encore ouvert");

        $this->app->make(AuthService::class)->validateGoogleUser('google-cfa', 'contact@ecole.example.com', UserRole::CFA);
    }

    public function test_an_existing_cfa_still_signs_in_with_google(): void
    {
        User::create(['email' => 'contact@ida.example.com', 'password_hash' => 'x', 'role' => UserRole::CFA]);

        $user = $this->app->make(AuthService::class)->validateGoogleUser('google-ida', 'contact@ida.example.com', UserRole::CFA);

        $this->assertSame(UserRole::CFA, $user->role);
        $this->assertSame('google-ida', $user->google_id);
    }

    public function test_the_google_redirect_sends_a_cfa_back_to_the_form(): void
    {
        Config::set('app.frontend_url', 'https://jeuncy.com');

        $this->get('/api/auth/google?role=CFA')
            ->assertRedirect('https://jeuncy.com/register?role=CFA');
    }

    public function test_registration_reopens_with_the_flag(): void
    {
        Config::set('services.jeuncy.inscription_cfa_ouverte', true);

        $this->postJson('/api/auth/register', ['email' => 'contact@cfa.example.com', 'password' => 'motdepasse', 'role' => 'CFA'])
            ->assertStatus(201);
    }
}
