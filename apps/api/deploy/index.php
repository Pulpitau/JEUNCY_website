<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// VERSION DE PRODUCTION — NE PAS ECRASER AVEC apps/api/public/index.php
//
// L'hebergement OVH mutualise impose une racine web publique (ici le dossier
// "api/"). Le reste de l'application vit a cote, dans "api-app/", hors de
// toute URL — c'est ce qui empeche quiconque de telecharger le .env, le code
// des services ou les dependances.
//
// Les chemins ci-dessous pointent donc vers ../api-app/ et non vers ../ comme
// dans le depot, ou public/ est un sous-dossier de l'application. Remplacer ce
// fichier par celui du depot fait chercher vendor/ au mauvais endroit : PHP
// s'arrete avant Laravel et toute l'API repond 500 avec un corps vide.

// Mode maintenance
if (file_exists($maintenance = __DIR__.'/../api-app/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Autoloader Composer
require __DIR__.'/../api-app/vendor/autoload.php';

// Demarrage de Laravel
/** @var Application $app */
$app = require_once __DIR__.'/../api-app/bootstrap/app.php';

$app->handleRequest(Request::capture());
