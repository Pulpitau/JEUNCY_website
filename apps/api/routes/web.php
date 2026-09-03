<?php

use App\Http\Controllers\DeployController;
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

// Deploiement sans SSH (voir DeployController) : routes inertes tant que
// DEPLOY_TOKEN n'est pas defini dans .env.
Route::get('/deploy/{token}/status', [DeployController::class, 'status']);
Route::get('/deploy/{token}/migrate', [DeployController::class, 'migrate']);
Route::get('/deploy/{token}/clear-cache', [DeployController::class, 'clearCache']);
Route::get('/deploy/{token}/env-check', [DeployController::class, 'envCheck']);
Route::get('/deploy/{token}/scheduler', [DeployController::class, 'scheduler']);
// Quelle version du code tourne reellement sur le serveur (voir le controleur).
Route::get('/deploy/{token}/version', [DeployController::class, 'version']);
// Execute les chemins sensibles et renvoie l'exception reelle : sans elle,
// une erreur de production n'est qu'une devinette (voir le controleur).
Route::get('/deploy/{token}/selftest', [DeployController::class, 'selfTest']);
