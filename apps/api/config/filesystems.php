<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            // Ecrit directement dans public/storage (dossier reel, pas un
            // symlink) : l'hebergement mutualise OVH ne fournit qu'un acces
            // FTP, donc `php artisan storage:link` n'est jamais executable
            // en production. En local, public/storage reste le symlink cree
            // par storage:link, donc ce chemin fonctionne aussi bien.
            //
            // PUBLIC_STORAGE_PATH : necessaire quand le dossier public/ est
            // deploye separement de l'app (staging/prod OVH, voir dossier
            // <site>-app a cote de <site> a la racine FTP) - public_path()
            // pointerait alors vers un dossier public/ inexistant a l'interieur
            // de l'app privee. Chemin absolu vers le dossier public reel dans
            // ce cas ; sinon repli sur public_path('storage') (local, ou tout
            // deploiement classique ou public/ reste dans l'app).
            'driver' => 'local',
            'root' => env('PUBLIC_STORAGE_PATH') ?: public_path('storage'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
