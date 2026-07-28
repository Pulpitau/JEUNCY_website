<?php

return [
    // dompdf resout par defaut son "chemin public" via base_path('public'), qui
    // pointe vers un dossier public/ inexistant a l'interieur de l'app privee
    // dans le deploiement split-folder (staging/prod OVH, voir <site>-app a cote
    // de <site> a la racine FTP) - realpath() echoue alors et
    // Barryvdh\DomPDF\ServiceProvider leve "Cannot resolve public path" avant
    // meme de generer le PDF. DOMPDF_PUBLIC_PATH : chemin absolu vers le dossier
    // public reel dans ce cas (meme valeur que le dossier parent de
    // PUBLIC_STORAGE_PATH, voir config/filesystems.php) ; laisser vide en local
    // ou tout deploiement classique ou public/ reste dans l'app.
    'public_path' => env('DOMPDF_PUBLIC_PATH'),
];
