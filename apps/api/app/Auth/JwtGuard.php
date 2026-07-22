<?php

namespace App\Auth;

use App\Models\User;
use App\Services\JwtService;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;

/**
 * Guard base sur l'access token JWT (header Authorization: Bearer ...), pas de
 * session. Equivalent du JwtAuthGuard NestJS precedent.
 */
class JwtGuard implements Guard
{
    use GuardHelpers;

    public function __construct(
        private readonly Request $request,
        private readonly JwtService $jwtService,
    ) {}

    public function user(): ?User
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $token = $this->request->bearerToken();
        if (! $token) {
            return null;
        }

        $payload = $this->jwtService->verifyAccessToken($token);
        if (! $payload) {
            return null;
        }

        $user = User::find($payload->sub);
        // Compte suspendu : on coupe l'acces immediatement, meme si l'access
        // token (courte duree) reste valide encore quelques minutes. Meme
        // logique pour token_version : un logout ou un reset de mot de passe
        // depuis l'emission de ce token le revoque immediatement.
        if (! $user || $user->is_suspended || ($payload->tv ?? null) !== $user->token_version) {
            return null;
        }

        return $this->user = $user;
    }

    public function validate(array $credentials = []): bool
    {
        return false;
    }
}
