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

        return $this->user = User::find($payload->sub);
    }

    public function validate(array $credentials = []): bool
    {
        return false;
    }
}
