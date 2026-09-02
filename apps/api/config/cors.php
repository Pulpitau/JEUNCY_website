<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    // Pas de wildcard : cookie de refresh httpOnly + credentials nécessitent une
    // origine explicite (CONVENTIONS.md section 11).
    'allowed_origins' => explode(',', env('FRONTEND_URL', 'http://localhost:5173')),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    // Content-Disposition : sans cette exposition explicite, le navigateur
    // cache cet en-tete au JavaScript sur une requete cross-origine (le
    // frontend et l'API sont sur deux domaines distincts en production).
    // Consequence concrete : le telechargement d'un CV depuis la CVtheque
    // retombait sur un nom generique au lieu du nom du fichier depose par le
    // candidat. Constate en navigateur, pas en test — un test cote serveur
    // voit l'en-tete, seul un vrai navigateur applique la restriction CORS.
    'exposed_headers' => ['Content-Disposition'],

    'max_age' => 0,

    'supports_credentials' => true,

];
