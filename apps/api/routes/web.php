<?php

use App\Http\Controllers\DeployController;
use App\Http\Controllers\NewsletterUnsubscribeController;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;

// API pure (voir apps/web pour le frontend React) : pas de vue Blade,
// juste un point de sonde minimal pour vérifier que le serveur répond.
Route::get('/', fn () => response()->json(['app' => 'Jeuncy API', 'status' => 'ok']));

// Logo Jeuncy pour les emails transactionnels (voir MailService::wrapEmailHtml) :
// Gmail et la plupart des clients mail ignorent les images data: URI en base64
// dans le HTML d'un email (contrairement au PDF/dompdf, ou ca fonctionne), il
// faut donc une vraie URL. Hors du groupe api.php (pas de Basic Auth staging
// dessus, contrairement au frontend) et hors auth:api (les clients mail ne
// s'authentifient pas). Sert directement depuis resources/ (fichier versionne
// dans le depot, present sur tous les environnements), pas depuis storage/
// (non versionne, absent du dossier "app" en deploiement split-folder OVH).
Route::get('/branding/logo.png', fn () => response()->file(
    resource_path('images/logo-jeuncy.png'),
    ['Cache-Control' => 'public, max-age=604800']
));

// Desinscription de la lettre hebdomadaire (voir
// NewsletterUnsubscribeController). Hors du groupe api.php : le lien est
// ouvert depuis une boite mail, pas par le frontend React, et il ne doit
// demander aucune authentification — on ne peut pas exiger d'etre connecte
// pour partir.
//
// 'signed:relative' et non 'signed' : la signature relative ne couvre pas le
// nom d'hote, donc le meme lien reste valable que le site reponde sur
// jeuncy.com, www.jeuncy.com ou api.jeuncy.com. Un lien de desinscription
// casse par un detail d'hote se decouvre par une plainte, jamais par un test.
Route::get('/newsletter/desinscription/{user}', [NewsletterUnsubscribeController::class, 'show'])
    ->middleware('signed:relative')
    ->whereNumber('user')
    ->name('newsletter.unsubscribe');

// C'est CE verbe qui desinscrit, jamais le GET (voir le controleur).
//
// Sans PreventRequestForgery : le POST « un clic » de Gmail et d'Outlook
// (RFC 8058) vient des serveurs de la boite mail, sans session ni jeton CSRF
// — et la page de confirmation elle-meme est servie a un visiteur anonyme.
// La signature de l'URL tient lieu de preuve d'origine, ce que le jeton CSRF
// ne saurait pas faire ici.
Route::post('/newsletter/desinscription/{user}', [NewsletterUnsubscribeController::class, 'unsubscribe'])
    ->middleware('signed:relative')
    ->whereNumber('user')
    ->withoutMiddleware([PreventRequestForgery::class])
    ->name('newsletter.unsubscribe.confirm');

// Deploiement sans SSH (voir DeployController) : routes inertes tant que
// DEPLOY_TOKEN n'est pas defini dans .env.
Route::get('/deploy/{token}/status', [DeployController::class, 'status']);
Route::get('/deploy/{token}/migrate', [DeployController::class, 'migrate']);
Route::get('/deploy/{token}/clear-cache', [DeployController::class, 'clearCache']);
Route::get('/deploy/{token}/env-check', [DeployController::class, 'envCheck']);
// Import La bonne alternance a la demande (au prochain passage du cron).
Route::get('/deploy/{token}/lba-import', [DeployController::class, 'lbaImport']);
// Relances du match : a blanc par defaut, ?executer=1 pour envoyer.
Route::get('/deploy/{token}/matches-remind', [DeployController::class, 'matchesRemind']);
Route::get('/deploy/{token}/scheduler', [DeployController::class, 'scheduler']);
// Quelle version du code tourne reellement sur le serveur (voir le controleur).
Route::get('/deploy/{token}/version', [DeployController::class, 'version']);
// Execute les chemins sensibles et renvoie l'exception reelle : sans elle,
// une erreur de production n'est qu'une devinette (voir le controleur).
Route::get('/deploy/{token}/selftest', [DeployController::class, 'selfTest']);
// Explique, candidat par candidat, pourquoi une offre le notifie ou non.
// Sans elle, un defaut de correspondance ne se diagnostique qu'a l'aveugle.
Route::get('/deploy/{token}/match/{jobOffer}', [DeployController::class, 'matchDebug'])->whereNumber('jobOffer');
// Rattrapage du geocodage (lot 1 du match). Compte seulement par defaut :
// ?executer=1 lance la passe. Idempotente, relancable autant que necessaire.
Route::get('/deploy/{token}/geocode-backfill', [DeployController::class, 'geocodeBackfill']);

// Journal d'erreurs de production (deploy-tools-30). Lecture seule, emails et
// jetons masques : sans lui, chaque bug signale par un etudiant se diagnostique
// par conjectures, au prix d'un aller-retour FTP par hypothese.
Route::get('/deploy/{token}/logs', [DeployController::class, 'logs']);

// Code postal d'une offre : une offre sans code postal n'a pas de coordonnees
// et reste hors du deck. Affiche l'offre sans ?cp=, l'ecrit et la geocode avec.
Route::get('/deploy/{token}/offre/{jobOffer}/code-postal', [DeployController::class, 'offerPostalCode'])->whereNumber('jobOffer');

// Lettre hebdomadaire. A BLANC par defaut : compte les destinataires et ne
// touche a rien. ?essai=1 envoie une seule copie a l'adresse de contact,
// ?envoyer=1&tous=1 envoie reellement (les deux parametres, jamais un seul).
Route::get('/deploy/{token}/newsletter', [DeployController::class, 'newsletter']);

// Depot des editions : le contenu de la lettre vit en base, pas en fichier,
// pour qu'une nouvelle edition ne demande aucun envoi FTP. La page liste les
// editions et porte le formulaire ; le POST depose, toujours en BROUILLON.
Route::match(['get', 'post'], '/deploy/{token}/newsletter/editions', [DeployController::class, 'newsletterEditions'])
    ->withoutMiddleware([PreventRequestForgery::class]);
// Armer / desarmer. En POST : une URL qui decide de ce qui part a 125
// personnes serait declenchee par le premier apercu de lien venu.
Route::post('/deploy/{token}/newsletter/editions/{slug}/statut', [DeployController::class, 'newsletterEditionStatus'])
    ->withoutMiddleware([PreventRequestForgery::class]);
// L'email COMPLET tel qu'il partira, coque et espaces reserves compris.
Route::get('/deploy/{token}/newsletter/editions/{slug}/apercu', [DeployController::class, 'newsletterEditionApercu']);
