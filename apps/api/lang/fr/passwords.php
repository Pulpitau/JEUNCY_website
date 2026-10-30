<?php

// Reinitialisation du mot de passe en francais (2026-10-30). Jeuncy utilise
// son propre jeton (AuthService::forgotPassword) : ces cles ne servent que
// si le broker de Laravel est un jour appele.

return [

    'reset' => 'Ton mot de passe a été réinitialisé.',
    'sent' => 'Nous t’avons envoyé un lien pour réinitialiser ton mot de passe.',
    'throttled' => 'Patiente un peu avant de réessayer.',
    'token' => 'Ce lien de réinitialisation n’est pas valide.',
    'user' => 'Aucun compte ne correspond à cette adresse email.',

];
