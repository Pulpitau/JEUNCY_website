<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Exceptions\ApiException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function __construct(
        private readonly JwtService $jwtService,
        private readonly MailService $mailService,
    ) {}

    public function validateCredentials(string $email, string $password): User
    {
        $user = User::where('email', $email)->first();

        // Meme message que mot de passe invalide : ne pas reveler si l'email existe.
        if (! $user || ! $user->password_hash || ! Hash::check($password, $user->password_hash)) {
            throw new ApiException('INVALID_CREDENTIALS', 'Email ou mot de passe incorrect.', 401);
        }

        $this->assertNotSuspended($user);
        $user->update(['last_login_at' => now()]);

        return $user;
    }

    public function register(string $email, string $password, UserRole $role): array
    {
        if (User::where('email', $email)->exists()) {
            throw new ApiException('EMAIL_ALREADY_EXISTS', 'Un compte existe déjà avec cet email.', 409);
        }

        $user = User::create([
            'email' => $email,
            'password_hash' => $password,
            'role' => $role,
            'last_login_at' => now(),
        ]);

        return ['user' => $user, 'tokens' => $this->issueTokens($user)];
    }

    public function issueTokens(User $user): array
    {
        return [
            'accessToken' => $this->jwtService->issueAccessToken($user),
            'refreshToken' => $this->jwtService->issueRefreshToken($user),
        ];
    }

    public function refreshTokens(string $refreshToken): array
    {
        $payload = $this->jwtService->verifyRefreshToken($refreshToken);
        if (! $payload) {
            throw new ApiException('INVALID_REFRESH_TOKEN', 'Session expirée, merci de te reconnecter.', 401);
        }

        $user = User::find($payload->sub);
        if (! $user) {
            throw new ApiException('INVALID_REFRESH_TOKEN', 'Session expirée, merci de te reconnecter.', 401);
        }
        // Le token porte la version au moment de son emission : si elle ne
        // correspond plus (logout ou reset de mot de passe entre-temps), le
        // refresh token est revoque meme s'il n'est pas encore expire.
        if (($payload->tv ?? null) !== $user->token_version) {
            throw new ApiException('INVALID_REFRESH_TOKEN', 'Session expirée, merci de te reconnecter.', 401);
        }
        // Compte suspendu apres coup : on coupe le rafraichissement plutot que de
        // laisser une session deja ouverte se prolonger indefiniment.
        $this->assertNotSuspended($user);

        return $this->issueTokens($user);
    }

    // Incrementer token_version revoque immediatement tout access/refresh
    // token deja emis pour ce compte (voir JwtGuard::user() et
    // refreshTokens() ci-dessus) — les JWT sont stateless, sans ca rien ne
    // permettait de faire expirer une session avant son terme naturel.
    public function logout(User $user): void
    {
        $user->increment('token_version');
    }

    public function validateGoogleUser(string $googleId, string $email): User
    {
        $existingByGoogleId = User::where('google_id', $googleId)->first();
        if ($existingByGoogleId) {
            $this->assertNotSuspended($existingByGoogleId);
            $existingByGoogleId->update(['last_login_at' => now()]);

            return $existingByGoogleId;
        }

        $existingByEmail = User::where('email', $email)->first();
        if ($existingByEmail) {
            $this->assertNotSuspended($existingByEmail);
            // Compte cree via email/mot de passe : on associe le compte Google.
            $existingByEmail->update(['google_id' => $googleId, 'last_login_at' => now()]);

            return $existingByEmail;
        }

        // Nouvelle inscription via Google : role CANDIDATE par defaut, modifiable
        // plus tard dans le profil (pas de choix de role dans le flux OAuth).
        return User::create([
            'email' => $email,
            'password_hash' => null,
            'role' => UserRole::CANDIDATE,
            'google_id' => $googleId,
            'last_login_at' => now(),
        ]);
    }

    public function forgotPassword(string $email): void
    {
        $user = User::where('email', $email)->first();
        // Ne jamais reveler si l'email existe : on repond succes dans tous les cas
        // (voir AuthController::forgotPassword).
        if (! $user) {
            return;
        }

        $token = $this->jwtService->issuePasswordResetToken($user);
        $resetUrl = rtrim(config('app.frontend_url'), '/')."/reset-password?token={$token}";
        $this->mailService->sendPasswordResetEmail($user->email, $resetUrl);
    }

    public function resetPassword(string $token, string $newPassword): void
    {
        $subject = $this->jwtService->decodeResetTokenSubject($token);
        $user = $subject ? User::find($subject) : null;
        if (! $user || ! $this->jwtService->verifyPasswordResetToken($token, $user)) {
            throw new ApiException('INVALID_RESET_TOKEN', 'Ce lien de réinitialisation est invalide ou a expiré.', 401);
        }

        // token_version incremente au passage : un mot de passe reinitialise
        // revoque aussi toute session (access/refresh token) deja ouverte.
        // Deux appels distincts : token_version n'est volontairement pas dans
        // le Fillable du modele (jamais modifiable par un client), donc
        // update() l'ignorerait silencieusement — increment() le contourne,
        // comme dans AuthService::logout().
        $user->update(['password_hash' => $newPassword]);
        $user->increment('token_version');
    }

    private function assertNotSuspended(User $user): void
    {
        if ($user->is_suspended) {
            throw new ApiException('ACCOUNT_SUSPENDED', 'Ce compte a été suspendu. Contacte le support Jeuncy.', 403);
        }
    }
}
