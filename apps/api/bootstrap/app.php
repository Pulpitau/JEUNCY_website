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
use Illuminate\Support\Facades\Cache;
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
        // POURQUOI CE MONTAGE PLUTOT QUE ->daily() / ->weekly().
        //
        // Laravel n'execute une tache que si son expression cron correspond a
        // la minute EXACTE ou schedule:run est lance, et il ne rattrape rien.
        // Or le cron OVH nous appelle a une minute arbitraire : mesure le
        // 2026-09-10, il passait a 09:41:03. Toutes les taches etaient donc
        // planifiees en minute 0 et AUCUNE n'a jamais tourne — pendant des
        // semaines, sans le moindre message d'erreur. Le battement, seul en
        // * * * * * donc toujours du, est ce qui l'a revele.
        //
        // On rend donc chaque tache toujours due, et c'est un marqueur en
        // cache qui garantit une passe par jour (ou par semaine). Ce montage
        // ne depend plus ni de la minute, ni de l'heure, ni meme de la
        // frequence a laquelle l'hebergeur nous appelle : seulement du fait
        // qu'il nous appelle. Choisir ->cron('* 0 * * *') aurait suffi pour un
        // cron horaire, mais aurait tout rate le jour ou OVH saute le passage
        // de minuit.
        $unePasseParPeriode = function (string $commande, string $periode) use ($schedule) {
            $cle = "planificateur.derniere_passe.{$commande}";
            $jeton = fn () => $periode === 'semaine'
                ? now()->startOfWeek()->toDateString()
                : now()->toDateString();

            return $schedule->command($commande)
                ->everyMinute()
                ->withoutOverlapping()
                ->when(fn () => Cache::get($cle) !== $jeton())
                // Marque APRES SUCCES uniquement : une commande qui echoue est
                // retentee au passage suivant du cron, au lieu d'etre sautee
                // pour toute la journee sans que personne ne le sache.
                ->onSuccess(fn () => Cache::forever($cle, $jeton()));
        };

        $unePasseParPeriode('job-offers:expire', 'jour');
        $unePasseParPeriode('job-offers:archive-expired-trials', 'jour');
        $unePasseParPeriode('cvs:archive-inactive', 'jour');
        // Rattrapage : les candidats deja inscrits avant qu'une offre ne soit
        // publiee sont prevenus a la publication, et ceux qui arrivent apres le
        // sont a la creation de leur profil. Restent ceux qui ne touchent plus a
        // leur profil — ce balayage les couvre. Idempotent (voir la commande).
        $unePasseParPeriode('job-offers:notify-matching-candidates', 'jour');
        // Applique reellement la duree de conservation de 3 ans annoncee aux
        // candidats dans la politique de confidentialite (section 4 ter).
        // Hebdomadaire et non quotidien : le delai se compte en annees, une
        // passe par semaine suffit largement.
        $unePasseParPeriode('cv-downloads:purge', 'semaine');
        // Les rappels de visio, eux, doivent partir aussi souvent que possible
        // (fenetre de rappel d'1h, voir SendVideoRoomReminders) : pas de
        // marqueur, la commande tourne a chaque passage du cron. Elle est
        // idempotente (reminder_sent_at), donc une frequence plus elevee ne
        // produirait pas de doublon.
        $schedule->command('video-rooms:send-reminders')
            ->everyMinute()
            ->withoutOverlapping();
        // BATTEMENT. Ecrit l'heure a CHAQUE passage de schedule:run, donc a
        // chaque declenchement reel du cron OVH. C'est la seule preuve
        // d'execution : schedule:list ne montre que l'intention et
        // afficherait exactement la meme chose si le cron etait arrete
        // (voir DeployController::scheduler, qui relit cette cle).
        //
        // La cle est ecrite en dur plutot que reprise de DeployController :
        // un fichier absent ne doit jamais pouvoir faire tomber le cron
        // entier. Elle doit rester identique a CLE_BATTEMENT la-bas.
        $schedule->call(fn () => Cache::forever('planificateur.dernier_passage', now()->toDateTimeString()))
            ->everyMinute()
            ->name('battement-planificateur')
            ->withoutOverlapping();
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
