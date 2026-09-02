<?php

namespace App\Enums;

// Provenance du PDF servi a un recruteur depuis la CVtheque. Ordre de
// priorite applique par CvthequeService::resolveCvFor : le candidat a
// toujours le dernier mot sur ce que voit le recruteur.
enum CvSource: string
{
    // Le PDF que le candidat a lui-meme depose (son CV Canva, Word...).
    // Prioritaire : c'est le document qu'il a choisi de presenter.
    case UPLOADED = 'UPLOADED';

    // Le dernier CV genere par Jeuncy et conserve dans generated_cvs.
    case GENERATED = 'GENERATED';

    // Aucun des deux : le PDF est fabrique a la demande depuis les donnees du
    // profil, sans etre conserve. Indispensable pour que la CVtheque soit
    // utile des le premier jour, y compris pour les profils deja en base qui
    // n'ont jamais clique sur "Generer mon CV".
    case ON_THE_FLY = 'ON_THE_FLY';
}
