<?php

namespace App\Http\Controllers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\HttpFoundation\Response;

/**
 * Point d'entree de deploiement pour un hebergement mutualise sans acces SSH
 * (OVH) : `php artisan migrate` ne peut pas etre lance depuis un terminal, ce
 * controleur l'expose donc via une route web protegee par un secret partage
 * (DEPLOY_TOKEN, jamais commite). Si la variable est vide, les deux routes
 * repondent 404 - inerte par defaut en local et tant qu'elle n'est pas
 * volontairement definie en production.
 */
class DeployController extends Controller
{
    private function assertAuthorized(string $token): void
    {
        $expected = config('app.deploy_token');

        if (! $expected || ! hash_equals($expected, $token)) {
            abort(404);
        }
    }

    public function status(string $token): Response
    {
        $this->assertAuthorized($token);

        Artisan::call('migrate:status');

        return response(Artisan::output(), 200, ['Content-Type' => 'text/plain']);
    }

    public function migrate(string $token): Response
    {
        $this->assertAuthorized($token);

        Artisan::call('migrate', ['--force' => true]);

        return response(Artisan::output(), 200, ['Content-Type' => 'text/plain']);
    }

    public function clearCache(string $token): Response
    {
        $this->assertAuthorized($token);

        Artisan::call('optimize:clear');

        return response(Artisan::output(), 200, ['Content-Type' => 'text/plain']);
    }

    // Etat reel des taches planifiees sur le serveur.
    //
    // Une tache cassee ne se voit pas : le cron OVH appelle cron-schedule.php
    // en silence, et si bootstrap/app.php n'a pas ete redeploye (il ne fait
    // partie d'aucun dossier qu'on envoie habituellement) ou si le fichier de
    // commande manque, rien ne s'execute et personne ne l'apprend — les
    // rappels de visio ne partent simplement jamais. Constate le 2026-08-17 :
    // impossible de savoir de l'exterieur si video-rooms:send-reminders etait
    // reellement planifie en production.
    //
    // Ce endpoint repond a deux questions distinctes, et c'est leur croisement
    // qui compte : la commande est-elle ENREGISTREE (le fichier existe), et
    // est-elle PLANIFIEE (bootstrap/app.php a jour) ? Une commande enregistree
    // mais non planifiee ne tournera jamais ; une commande planifiee mais non
    // enregistree fait echouer schedule:run en entier, donc aussi les autres
    // taches.
    public function scheduler(string $token): Response
    {
        $this->assertAuthorized($token);

        // Passer par schedule:list plutot que de lire app(Schedule::class)
        // directement : withSchedule() s'accroche a Artisan::starting(), donc
        // le planificateur est VIDE tant que la console n'a pas demarre. Lu
        // depuis une requete web, il aurait toujours paru vide et ce controle
        // aurait annonce "non planifiee" meme quand tout fonctionne — piege
        // rencontre en ecrivant ce endpoint. Artisan::call() demarre la
        // console, donc declenche le peuplement.
        Artisan::call('schedule:list');
        $sortie = Artisan::output();

        $planifiees = collect(explode("\n", $sortie))
            ->map(fn (string $ligne) => trim($ligne))
            ->filter()
            ->all();

        return response()->json([
            'taches' => self::comparerTaches(array_keys(Artisan::all()), $planifiees),
            'sortie_brute' => $sortie,
            'heure_serveur' => now()->toDateTimeString(),
        ]);
    }

    // Taches que l'application est censee executer. Toute nouvelle commande
    // planifiee doit etre ajoutee ici, sinon son absence en production passera
    // inapercue — c'est exactement le scenario que ce controle previent.
    public const TACHES_ATTENDUES = [
        'job-offers:expire',
        'job-offers:archive-expired-trials',
        'cvs:archive-inactive',
        'video-rooms:send-reminders',
        'cv-downloads:purge',
    ];

    // Extrait du controleur pour etre testable sur des entrees choisies : le
    // planificateur est peuple au demarrage de Laravel et ne peut pas etre vide
    // proprement depuis un test HTTP, or c'est justement le cas degrade qu'il
    // faut couvrir.
    public static function comparerTaches(array $enregistrees, array $planifiees): array
    {
        $rapport = [];

        foreach (self::TACHES_ATTENDUES as $commande) {
            $estEnregistree = in_array($commande, $enregistrees, true);
            $estPlanifiee = (bool) collect($planifiees)->first(fn (string $c) => str_contains($c, $commande));

            $rapport[$commande] = match (true) {
                $estEnregistree && $estPlanifiee => 'ok',
                ! $estEnregistree && $estPlanifiee => 'PLANIFIEE MAIS FICHIER DE COMMANDE ABSENT — casse tout schedule:run',
                $estEnregistree && ! $estPlanifiee => 'presente mais NON PLANIFIEE — ne tournera jamais (bootstrap/app.php a redeployer)',
                default => 'ABSENTE des deux cotes',
            };
        }

        return $rapport;
    }

    // Verifie la presence (pas la valeur) des variables d'environnement dont
    // depend une fonctionnalite reelle de l'app. Sert a detecter en 5 secondes
    // un .env de staging/prod desynchronise du .env local (ex: cle Resend
    // jamais copiee sur le serveur, oubliee lors d'un ajout de config) sans
    // avoir a ouvrir le fichier a la main via WinSCP - pas d'acces SSH sur cet
    // hebergement pour verifier autrement.
    public function envCheck(string $token): Response
    {
        $this->assertAuthorized($token);

        $keys = [
            'APP_URL',
            'FRONTEND_URL',
            'JWT_SECRET',
            'JWT_REFRESH_SECRET',
            'GOOGLE_CLIENT_ID',
            'GOOGLE_CLIENT_SECRET',
            'GOOGLE_REDIRECT_URI',
            'STRIPE_SECRET_KEY',
            'STRIPE_WEBHOOK_SECRET',
            'STRIPE_COMPANY_OFFER_PRICE_CENTS',
            'STRIPE_CFA_OFFER_PRICE_CENTS',
            'STRIPE_COMPANY_SUBSCRIPTION_PRICE_CENTS',
            'STRIPE_CFA_SUBSCRIPTION_PRICE_CENTS',
            'RESEND_API_KEY',
            'RESEND_FROM_EMAIL',
        ];

        $report = collect($keys)
            ->mapWithKeys(fn (string $key) => [$key => filled(env($key)) ? 'ok' : 'MANQUANT'])
            ->all();

        // Les tarifs ne se contentent pas d'etre presents : ils doivent avoir la
        // BONNE valeur. Un .env de prod reste sur ses anciens montants apres une
        // hausse de tarif (la valeur par defaut du config n'entre en jeu que si
        // la ligne est absente, pas si elle est presente et perimee) — c'est
        // ainsi qu'on facturerait 79€ au lieu de 499€ sans s'en apercevoir.
        $report['_montants_factures'] = [
            'abonnement_entreprise' => config('services.stripe.company_subscription_price_cents').' centimes',
            'abonnement_cfa' => config('services.stripe.cfa_subscription_price_cents').' centimes',
            'tarif_fondateur' => config('services.stripe.founder_subscription_price_cents').' centimes',
            'places_fondateur' => config('services.stripe.founder_seats_total'),
            'offre_entreprise' => config('services.stripe.company_offer_price_cents').' centimes',
            'offre_cfa' => config('services.stripe.cfa_offer_price_cents').' centimes',
        ];

        return response()->json($report);
    }
}
