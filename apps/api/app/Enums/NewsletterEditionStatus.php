<?php

namespace App\Enums;

/**
 * Cycle de vie d'une edition de la lettre hebdomadaire.
 *
 * C'EST CE QUI AUTORISE L'AUTOMATISATION. Le planificateur envoie, chaque
 * lundi, l'edition PRETE — et rien d'autre. Un texte en cours d'ecriture reste
 * BROUILLON et ne peut pas partir par accident ; une edition deja partie est
 * ENVOYEE et ne peut pas repartir. L'absence d'edition PRETE un lundi donne
 * donc une semaine sans lettre, jamais une vieille lettre renvoyee : c'est le
 * bon sens de la defaillance pour un message visible par 125 personnes.
 *
 * Colonne `string` et non `enum` MySQL : ajouter un etat demanderait sinon une
 * migration d'enum, exactement le fichier qu'on oublie de deployer (lecon du
 * 2026-09-08).
 */
enum NewsletterEditionStatus: string
{
    /** En cours d'ecriture. Le planificateur ne la voit pas. */
    case BROUILLON = 'BROUILLON';

    /** Relue et armee : c'est elle que le prochain lundi enverra. */
    case PRETE = 'PRETE';

    /** Partie. Elle ne peut plus etre renvoyee, par aucun chemin. */
    case ENVOYEE = 'ENVOYEE';

    /**
     * La passe s'est terminee sans qu'un seul message ne parte.
     *
     * Distinct d'ENVOYEE a dessein : les deux ont fini, mais seul ce cas
     * demande qu'on aille regarder pourquoi. Le confondre avec ENVOYEE
     * aurait laisse croire qu'une lettre etait partie alors que personne ne
     * l'a recue.
     */
    case ECHOUEE = 'ECHOUEE';
}
