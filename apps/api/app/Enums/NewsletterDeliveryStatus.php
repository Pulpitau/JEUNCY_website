<?php

namespace App\Enums;

/**
 * Etat d'un envoi de la lettre hebdomadaire, par edition et par destinataire.
 *
 * TROIS ETATS ET PAS DEUX. PENDING existe parce que la ligne est ecrite AVANT
 * l'appel a Resend : si le processus est coupe entre l'ecriture et la reponse
 * (temps d'execution PHP epuise sur l'hebergement mutualise, ce qui arrive),
 * on ne sait plus si le message est parti. La ligne reste alors PENDING, et la
 * selection des destinataires l'ecarte : en cas de doute, on n'ecrit pas deux
 * fois a la meme personne. L'inverse — ecrire la ligne apres l'envoi — aurait
 * reexpedie la lettre a tous ceux que la coupure a laisses sans trace.
 *
 * Colonne `string` et non `enum` MySQL : ajouter un etat demanderait alors une
 * migration d'enum, exactement le fichier qu'on oublie de deployer (lecon du
 * 2026-09-08, ou NotificationType sans sa valeur faisait echouer l'insertion
 * en silence).
 */
enum NewsletterDeliveryStatus: string
{
    /** Reserve, resultat inconnu : ni renvoye, ni compte comme recu. */
    case PENDING = 'PENDING';

    /** Resend a accepte le message. */
    case SENT = 'SENT';

    /** Resend a refuse ou n'a pas repondu. La raison est dans `error`. */
    case FAILED = 'FAILED';
}
