<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Equivalent du RolesGuard NestJS precedent : verifie que l'utilisateur
 * authentifie (auth:api) a l'un des roles autorises pour cette route.
 * Usage : ->middleware('role:ADMIN,COMPANY')
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role->value, $roles, true)) {
            abort(403, 'Accès refusé.');
        }

        return $next($request);
    }
}
