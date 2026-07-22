<?php

namespace App\Services;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Throwable;

/**
 * Emission/verification des JWT. Access token courte duree (payload sub/email/role)
 * et refresh token longue duree (payload sub uniquement), secrets separes.
 * Meme architecture que la version NestJS precedente (voir CLAUDE.md section 11).
 */
class JwtService
{
    private const PASSWORD_RESET_PURPOSE = 'password-reset';

    public function issueAccessToken(User $user): string
    {
        $now = time();

        return JWT::encode([
            'sub' => $user->id,
            'email' => $user->email,
            'role' => $user->role->value,
            'iat' => $now,
            'exp' => $now + config('jwt.ttl_minutes') * 60,
        ], config('jwt.secret'), 'HS256');
    }

    public function issueRefreshToken(User $user): string
    {
        $now = time();

        return JWT::encode([
            'sub' => $user->id,
            'iat' => $now,
            'exp' => $now + config('jwt.refresh_ttl_minutes') * 60,
        ], config('jwt.refresh_secret'), 'HS256');
    }

    public function verifyAccessToken(string $token): ?object
    {
        return $this->decode($token, config('jwt.secret'));
    }

    public function verifyRefreshToken(string $token): ?object
    {
        return $this->decode($token, config('jwt.refresh_secret'));
    }

    public function issuePasswordResetToken(User $user): string
    {
        $now = time();

        return JWT::encode([
            'sub' => $user->id,
            'purpose' => self::PASSWORD_RESET_PURPOSE,
            'iat' => $now,
            'exp' => $now + 3600,
        ], config('jwt.secret'), 'HS256');
    }

    public function verifyPasswordResetToken(string $token): ?object
    {
        $decoded = $this->decode($token, config('jwt.secret'));
        if (! $decoded || ($decoded->purpose ?? null) !== self::PASSWORD_RESET_PURPOSE) {
            return null;
        }

        return $decoded;
    }

    private function decode(string $token, string $secret): ?object
    {
        try {
            return JWT::decode($token, new Key($secret, 'HS256'));
        } catch (Throwable) {
            return null;
        }
    }
}
