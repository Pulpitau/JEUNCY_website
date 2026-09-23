<?php

namespace App\Enums;

/**
 * Etages de la cascade de relances (MOBILE.md §5).
 *
 * Quatre situations distinctes, chacune avec son destinataire et sa suite.
 * Le nom dit QUI attend quoi, pas le nombre de jours : les delais bougeront
 * avec les premiers chiffres du pilote, la situation, elle, ne bougera pas.
 *
 * L'ordre des cas est celui de la cascade, et `suivant()` s'en sert : une
 * ligne ne peut avancer que d'un etage par passage, jamais sauter.
 */
enum MatchReminderStage: string
{
    // --- Interet employeur, le candidat n'a pas repondu -------------------
    /** J+3 : « une entreprise t'attend ». */
    case EMPLOYER_INTEREST_D3 = 'EMPLOYER_INTEREST_D3';
    /** J+14 : la ligne est fermee, plus personne n'attend. */
    case EMPLOYER_INTEREST_EXPIRED = 'EMPLOYER_INTEREST_EXPIRED';

    // --- Interet candidat, l'employeur n'a pas repondu --------------------
    /**
     * J+7 : le candidat est invite a envoyer son dossier directement.
     *
     * L'employeur ne recoit RIEN de nominatif a cet etage : il n'a jamais
     * su que ce candidat s'interessait a lui (§5), et le lui apprendre par
     * une relance revelerait un geste que le produit n'a pas montre.
     */
    case CANDIDATE_INTEREST_D7 = 'CANDIDATE_INTEREST_D7';
    case CANDIDATE_INTEREST_EXPIRED = 'CANDIDATE_INTEREST_EXPIRED';

    // --- Match sans dossier ----------------------------------------------
    /** J+2 puis J+7 : « envoie ton dossier », au candidat. */
    case MATCH_NO_APPLICATION_D2 = 'MATCH_NO_APPLICATION_D2';
    case MATCH_NO_APPLICATION_D7 = 'MATCH_NO_APPLICATION_D7';
    /** J+30 : le match se ferme faute de dossier. */
    case MATCH_NO_APPLICATION_EXPIRED = 'MATCH_NO_APPLICATION_EXPIRED';

    // --- Dossier sans reponse de l'employeur ------------------------------
    /** J+3 : rappel a l'employeur (in-app + email). */
    case APPLICATION_SILENT_D3 = 'APPLICATION_SILENT_D3';
    /** J+7 : l'employeur entre dans l'onglet admin, le candidat est prevenu. */
    case APPLICATION_SILENT_D7 = 'APPLICATION_SILENT_D7';
    /** J+14 : « Jeuncy a relance l'entreprise ». */
    case APPLICATION_SILENT_D14 = 'APPLICATION_SILENT_D14';
    /**
     * J+30 : cloture par Jeuncy.
     *
     * Message standard au candidat, et JAMAIS un statut pose au nom de
     * l'entreprise : Jeuncy peut dire « on n'a pas eu de reponse », il ne
     * peut pas dire « vous etes refuse » a la place de quelqu'un.
     */
    case APPLICATION_SILENT_CLOSED = 'APPLICATION_SILENT_CLOSED';

    /** Cet etage termine sa cascade : plus rien ne partira. */
    public function isFinal(): bool
    {
        return in_array($this, [
            self::EMPLOYER_INTEREST_EXPIRED,
            self::CANDIDATE_INTEREST_EXPIRED,
            self::MATCH_NO_APPLICATION_EXPIRED,
            self::APPLICATION_SILENT_CLOSED,
        ], true);
    }
}
