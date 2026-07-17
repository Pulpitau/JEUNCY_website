<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Services\AuthService;
use App\Services\JwtService;
use App\Services\MailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    private AuthService $authService;

    private $mailServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mailServiceMock = Mockery::mock(MailService::class);
        $this->app->instance(MailService::class, $this->mailServiceMock);
        $this->authService = $this->app->make(AuthService::class);
    }

    public function test_validate_credentials_rejects_unknown_email(): void
    {
        $this->expectException(ApiException::class);
        $this->authService->validateCredentials('inconnu@example.com', 'password123');
    }

    public function test_validate_credentials_rejects_account_without_password(): void
    {
        User::create(['email' => 'lea@example.com', 'password_hash' => null, 'role' => UserRole::CANDIDATE]);

        $this->expectException(ApiException::class);
        $this->authService->validateCredentials('lea@example.com', 'password123');
    }

    public function test_validate_credentials_rejects_wrong_password(): void
    {
        User::create(['email' => 'lea@example.com', 'password_hash' => 'bonMotDePasse', 'role' => UserRole::CANDIDATE]);

        $this->expectException(ApiException::class);
        $this->authService->validateCredentials('lea@example.com', 'mauvaisMotDePasse');
    }

    public function test_validate_credentials_returns_user_for_correct_password(): void
    {
        $user = User::create(['email' => 'lea@example.com', 'password_hash' => 'bonMotDePasse', 'role' => UserRole::CANDIDATE]);

        $result = $this->authService->validateCredentials('lea@example.com', 'bonMotDePasse');

        $this->assertTrue($result->is($user));
    }

    public function test_register_refuses_duplicate_email(): void
    {
        User::create(['email' => 'lea@example.com', 'password_hash' => 'x', 'role' => UserRole::CANDIDATE]);

        $this->expectException(ApiException::class);
        $this->authService->register('lea@example.com', 'password123', UserRole::CANDIDATE);
    }

    public function test_register_creates_user_with_hashed_password_and_tokens(): void
    {
        $result = $this->authService->register('lea@example.com', 'password123', UserRole::CANDIDATE);

        $this->assertNotEquals('password123', $result['user']->password_hash);
        $this->assertTrue(Hash::check('password123', $result['user']->password_hash));
        $this->assertNotEmpty($result['tokens']['accessToken']);
        $this->assertNotEmpty($result['tokens']['refreshToken']);
    }

    public function test_refresh_tokens_rejects_invalid_token(): void
    {
        $this->expectException(ApiException::class);
        $this->authService->refreshTokens('not-a-valid-token');
    }

    public function test_refresh_tokens_rejects_token_of_deleted_user(): void
    {
        $user = User::create(['email' => 'lea@example.com', 'password_hash' => 'x', 'role' => UserRole::CANDIDATE]);
        $tokens = $this->authService->issueTokens($user);
        $user->delete();

        $this->expectException(ApiException::class);
        $this->authService->refreshTokens($tokens['refreshToken']);
    }

    public function test_refresh_tokens_issues_new_pair_for_valid_token(): void
    {
        $user = User::create(['email' => 'lea@example.com', 'password_hash' => 'x', 'role' => UserRole::CANDIDATE]);
        $tokens = $this->authService->issueTokens($user);

        $newTokens = $this->authService->refreshTokens($tokens['refreshToken']);

        $this->assertNotEmpty($newTokens['accessToken']);
        $this->assertNotEmpty($newTokens['refreshToken']);
    }

    public function test_forgot_password_does_nothing_for_unknown_email(): void
    {
        $this->mailServiceMock->shouldNotReceive('sendPasswordResetEmail');

        $this->authService->forgotPassword('inconnu@example.com');
    }

    public function test_forgot_password_sends_email_for_existing_account(): void
    {
        User::create(['email' => 'lea@example.com', 'password_hash' => 'x', 'role' => UserRole::CANDIDATE]);

        $this->mailServiceMock->shouldReceive('sendPasswordResetEmail')
            ->once()
            ->with('lea@example.com', Mockery::pattern('#/reset-password\?token=#'));

        $this->authService->forgotPassword('lea@example.com');
    }

    public function test_reset_password_rejects_token_with_wrong_purpose(): void
    {
        $user = User::create(['email' => 'lea@example.com', 'password_hash' => 'x', 'role' => UserRole::CANDIDATE]);
        $accessToken = $this->authService->issueTokens($user)['accessToken'];

        $this->expectException(ApiException::class);
        $this->authService->resetPassword($accessToken, 'nouveauMotDePasse');
    }

    public function test_reset_password_updates_password_for_valid_token(): void
    {
        $user = User::create(['email' => 'lea@example.com', 'password_hash' => 'x', 'role' => UserRole::CANDIDATE]);
        $token = $this->app->make(JwtService::class)->issuePasswordResetToken($user);

        $this->authService->resetPassword($token, 'nouveauMotDePasse');

        $this->assertTrue(Hash::check('nouveauMotDePasse', $user->fresh()->password_hash));
    }
}
