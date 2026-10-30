<?php

// Messages d'authentification en francais (2026-10-30). Jeuncy ne passe pas
// par les vues d'authentification de Laravel, mais ces cles servent de repli
// a tout ce qui les appelle (limiteur de connexion, garde de mot de passe).

return [

    'failed' => 'Email ou mot de passe incorrect.',
    'password' => 'Le mot de passe est incorrect.',
    'throttle' => 'Trop de tentatives de connexion. Réessaie dans :seconds secondes.',

];
