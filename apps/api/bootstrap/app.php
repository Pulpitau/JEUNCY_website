<?php

use App\Exceptions\ApiException;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\WrapApiResponse;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('job-offers:expire')->daily();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(append: [
            WrapApiResponse::class,
        ]);
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);
        // API pure, aucune route 'login' web : ne jamais rediriger un invite,
        // toujours lever AuthenticationException (rendue en JSON 401 ci-dessous).
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Erreurs metier attendues (mauvais mot de passe, email deja pris, etc.) :
        // pas de bruit dans les logs, seules les exceptions non gerees le sont.
        $exceptions->dontReport(ApiException::class);

        // Uniformise toutes les erreurs API au format { success: false, error: { code, message } }
        // defini dans CONVENTIONS.md section 6.
        $exceptions->render(function (ApiException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'error' => ['code' => $e->errorCode, 'message' => $e->getMessage()],
            ], $e->getStatusCode());
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_INPUT',
                    'message' => implode(' ', $e->validator->errors()->all()),
                ],
            ], 400);
        });

        // Filet de securite pour tout ce qui n'est pas deja au bon format
        // (404 route inconnue, 401 non authentifie, 500 non gere, etc.)
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            if (! $request->is('api/*') || ! $response instanceof JsonResponse) {
                return $response;
            }

            $data = $response->getData(true);
            if (is_array($data) && array_key_exists('success', $data)) {
                return $response;
            }

            $status = $response->getStatusCode();
            $defaultCodes = [
                400 => 'INVALID_INPUT',
                401 => 'UNAUTHORIZED',
                403 => 'FORBIDDEN',
                404 => 'NOT_FOUND',
                405 => 'METHOD_NOT_ALLOWED',
                409 => 'CONFLICT',
                500 => 'INTERNAL_ERROR',
            ];

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => $defaultCodes[$status] ?? 'INTERNAL_ERROR',
                    'message' => $data['message'] ?? 'Une erreur est survenue.',
                ],
            ], $status);
        });
    })->create();
