<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    private const REFRESH_COOKIE_NAME = 'jeuncy_refresh_token';

    private const REFRESH_COOKIE_PATH = '/api/auth';

    public function __construct(private readonly AuthService $authService) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $result = $this->authService->register(
            $validated['email'],
            $validated['password'],
            UserRole::from($validated['role']),
        );

        return $this->respondWithTokens($result['user'], $result['tokens'], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $this->authService->validateCredentials($validated['email'], $validated['password']);
        $tokens = $this->authService->issueTokens($user);

        return $this->respondWithTokens($user, $tokens, 200);
    }

    public function refresh(Request $request): JsonResponse
    {
        $refreshToken = $request->cookie(self::REFRESH_COOKIE_NAME);
        if (! $refreshToken) {
            throw new ApiException('MISSING_REFRESH_TOKEN', 'Aucune session active.', 400);
        }

        $tokens = $this->authService->refreshTokens($refreshToken);

        return response()
            ->json(['accessToken' => $tokens['accessToken']])
            ->cookie($this->makeRefreshCookie($tokens['refreshToken']));
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return response()
            ->json(['loggedOut' => true])
            ->cookie(Cookie::forget(self::REFRESH_COOKIE_NAME, self::REFRESH_COOKIE_PATH));
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->authService->forgotPassword($request->validated('email'));

        // Reponse identique que l'email existe ou non (ne pas reveler les comptes existants).
        return response()->json(['message' => 'Si ce compte existe, un email a été envoyé.']);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $this->authService->resetPassword($validated['token'], $validated['newPassword']);

        return response()->json(['message' => 'Mot de passe mis à jour.']);
    }

    // Le type de compte choisi (Candidat/Entreprise/CFA sur la page
    // d'inscription) est transmis via le parametre OAuth standard "state" —
    // en mode stateless() Socialite ne l'utilise plus pour son propre CSRF
    // (deja le cas avant ce changement), on peut donc s'en servir pour
    // vehiculer cette info a travers l'aller-retour vers Google (aucun
    // parametre personnalise n'est jamais renvoye par Google, seul "state"
    // est echo verbatim). Repris cote callback ci-dessous, valide a nouveau
    // contre UserRole avant usage (voir AuthService::validateGoogleUser).
    public function googleRedirect(Request $request): RedirectResponse
    {
        $requestedRole = UserRole::tryFrom((string) $request->query('role'));
        $role = $requestedRole && $requestedRole !== UserRole::ADMIN ? $requestedRole : UserRole::CANDIDATE;

        return Socialite::driver('google')->stateless()->with(['state' => $role->value])->redirect();
    }

    public function googleCallback(Request $request): RedirectResponse
    {
        $googleUser = Socialite::driver('google')->stateless()->user();
        $requestedRole = UserRole::tryFrom((string) $request->query('state'));
        // ADMIN reste exclu meme si quelqu'un forge la valeur de "state" —
        // meme garde qu'a l'inscription classique (voir RegisterRequest).
        $role = $requestedRole && $requestedRole !== UserRole::ADMIN ? $requestedRole : UserRole::CANDIDATE;
        $user = $this->authService->validateGoogleUser($googleUser->getId(), $googleUser->getEmail(), $role);
        $tokens = $this->authService->issueTokens($user);

        // Pas d'access token dans l'URL (evite qu'il finisse dans l'historique du
        // navigateur) : le cookie httpOnly pose ici suffit, la page /auth/callback
        // appelle /auth/refresh comme au demarrage normal de l'app.
        return redirect(rtrim(config('app.frontend_url'), '/').'/auth/callback')
            ->cookie($this->makeRefreshCookie($tokens['refreshToken']));
    }

    private function respondWithTokens(User $user, array $tokens, int $status): JsonResponse
    {
        return response()
            ->json(['user' => $user, 'accessToken' => $tokens['accessToken']], $status)
            ->cookie($this->makeRefreshCookie($tokens['refreshToken']));
    }

    private function makeRefreshCookie(string $refreshToken): \Symfony\Component\HttpFoundation\Cookie
    {
        return cookie(
            name: self::REFRESH_COOKIE_NAME,
            value: $refreshToken,
            minutes: config('jwt.refresh_ttl_minutes'),
            path: self::REFRESH_COOKIE_PATH,
            secure: app()->isProduction(),
            httpOnly: true,
            sameSite: 'lax',
        );
    }
}
