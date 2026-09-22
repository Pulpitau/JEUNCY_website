<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Seize ans minimum sur toute route du match, quel que soit le client
 * (decision de Pierre du 2026-09-22 : le site reste a 15 ans, l'application
 * passe a 16).
 *
 * POURQUOI UN MIDDLEWARE ET NON UNE GARDE DANS CHAQUE SERVICE. Cette regle
 * ne souffre aucune exception et doit tenir sur une route ajoutee demain par
 * quelqu'un qui n'aura pas lu MOBILE.md. Posee sur le groupe de routes, elle
 * tient toute seule ; repartie dans six services, elle finirait par manquer
 * dans le septieme.
 *
 * Les autres roles passent : leurs gardes a eux (entreprise verifiee,
 * perimetre departemental) vivent dans les services, et un employeur n'a pas
 * de date de naissance.
 */
class EnsureMatchAge
{
    public const MIN_AGE = 16;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->role !== UserRole::CANDIDATE) {
            return $next($request);
        }

        $profile = $user->candidateProfile;

        if ($profile === null) {
            throw new ApiException(
                'CANDIDATE_PROFILE_REQUIRED',
                'Crée ton profil pour découvrir des offres.',
                403,
            );
        }

        if ($profile->birth_date === null) {
            throw new ApiException(
                'BIRTH_DATE_REQUIRED',
                'Indique ta date de naissance pour découvrir des offres.',
                403,
            );
        }

        if ($profile->birth_date->age < self::MIN_AGE) {
            throw new ApiException(
                'MATCH_MIN_AGE',
                'Découvrir est réservé aux 16 ans et plus.',
                403,
            );
        }

        return $next($request);
    }
}
