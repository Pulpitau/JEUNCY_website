<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Exceptions\ApiException;
use App\Models\CfaOrganization;
use App\Models\Company;
use App\Models\User;
use App\Rules\ValidSiret;

/**
 * Porte d'entree du cote employeur (MOBILE.md §4.0) : tant qu'une
 * organisation n'est pas VERIFIED, elle ne voit aucun candidat — ni deck, ni
 * CVtheque, ni candidatures recues, et elle ne peut marquer aucun interet.
 *
 * Regle absolue : jamais VERIFIED par defaut. Un registre des entreprises
 * muet (panne, timeout) laisse la fiche PENDING. Le contraire — « on ne sait
 * pas, donc on autorise » — mettrait des cartes de mineurs sous les yeux de
 * n'importe qui le jour ou api.gouv.fr tombe.
 *
 * Limite assumee et documentee (docs/mobile/lot-1-backend.md §10) : un SIRET
 * public actif suffit a etre verifie automatiquement. C'est une preuve
 * d'existence, pas une preuve d'identite. Avant d'ouvrir au-dela d'IDA, il
 * faudra au minimum l'email de domaine ou un clic admin.
 */
class CompanyVerificationService
{
    public function __construct(
        private readonly TrainingOrganizationDetector $trainingOrganizationDetector,
    ) {}

    /**
     * Verifie (ou re-verifie) une organisation et ECRIT son statut.
     *
     * Affectation directe + save : verification_status et ses compagnons
     * sont hors fillable, une organisation ne se declare pas verifiee
     * elle-meme.
     */
    public function verify(Company|CfaOrganization $organization): VerificationStatus
    {
        [$status, $note] = $this->evaluate($organization->siret, $organization instanceof Company);

        $organization->verification_status = $status;
        $organization->verification_note = $note;
        $organization->verified_at = $status === VerificationStatus::VERIFIED ? now() : null;
        // null = verification automatique. Un clic admin (lot ulterieur)
        // posera l'id de l'admin qui a tranche.
        $organization->verified_by = null;
        $organization->save();

        return $status;
    }

    /**
     * @return array{0: VerificationStatus, 1: string|null}
     */
    private function evaluate(?string $siret, bool $refuseTrainingNaf): array
    {
        $digits = preg_replace('/\D/', '', (string) $siret) ?? '';

        if ($digits === '') {
            return [VerificationStatus::PENDING, 'SIRET manquant : la vérification ne peut pas être faite.'];
        }

        if (! ValidSiret::isValid($digits)) {
            return [VerificationStatus::REJECTED, 'SIRET invalide'];
        }

        $establishment = $this->trainingOrganizationDetector->lookupEstablishment($digits);

        // Registre muet : ni trouve, ni refuse. On attend, on n'ouvre pas.
        if ($establishment === null) {
            return [VerificationStatus::PENDING, 'Vérification en attente : le registre des entreprises est indisponible ou ne connaît pas ce SIRET.'];
        }

        // Uniquement pour une ENTREPRISE. Un CFA a par definition un code NAF
        // d'enseignement : lui appliquer la meme regle refuserait l'ecole
        // partenaire (IDA), c'est-a-dire le seul CFA que Jeuncy accepte.
        // Ecart assume par rapport au contrat, qui ne distingue pas les deux.
        //
        // Place AVANT la garde d'appariement : le NAF du siege est deja un
        // signal suffisant pour refuser une ecole, meme si l'etablissement
        // exact n'a pas ete retrouve. On refuse large, on ouvre etroit.
        if ($refuseTrainingNaf && TrainingOrganizationDetector::isBlockedNaf($establishment['naf'] ?? null)) {
            return [VerificationStatus::REJECTED, "Activité principale « enseignement » au registre des entreprises : l'espace entreprise n'est pas ouvert aux organismes de formation."];
        }

        // Le registre a repondu, mais sur l'unite legale, pas sur
        // l'etablissement demande : la recherche est plein texte, un numero
        // invente dont les neuf premiers chiffres forment un vrai SIREN rend
        // le siege. Accepter ce repli suffirait a se faire verifier avec un
        // SIRET qui n'existe pas — c'est la seule porte entre un inconnu et
        // des fiches de mineurs.
        if (($establishment['matched'] ?? false) !== true) {
            return [VerificationStatus::PENDING, "Vérification en attente : ce numéro SIRET n'a pas été retrouvé au registre des entreprises."];
        }

        if (($establishment['active'] ?? false) !== true) {
            return [VerificationStatus::REJECTED, 'Établissement fermé au registre des entreprises.'];
        }

        return [VerificationStatus::VERIFIED, null];
    }

    /**
     * Le detecteur d'ecoles PORTE PAR CE SERVICE.
     *
     * CompanyService et CfaOrganizationService l'empruntent ici plutot que de
     * s'en faire injecter un a eux : la creation d'une fiche appelle le
     * registre une premiere fois pour la detection d'ecole, puis une seconde
     * pour la verification. Le detecteur memorise ses reponses par SIRET, mais
     * seulement pour lui-meme — deux instances, deux appels reseau, et une
     * entreprise qui attend huit secondes au lieu de quatre.
     */
    public function detector(): TrainingOrganizationDetector
    {
        return $this->trainingOrganizationDetector;
    }

    public function organizationFor(User $user): Company|CfaOrganization|null
    {
        return match ($user->role) {
            UserRole::COMPANY => $user->company,
            UserRole::CFA => $user->cfaOrganization,
            default => null,
        };
    }

    // ADMIN et STAFF passent : ce sont des acces internes (meme raisonnement
    // que routes/api/cvtheque.php), ils n'ont pas d'organisation a verifier.
    public function isVerified(User $user): bool
    {
        if (in_array($user->role, [UserRole::ADMIN, UserRole::STAFF], true)) {
            return true;
        }

        return $this->organizationFor($user)?->isVerified() === true;
    }

    public function requireVerified(User $user): void
    {
        if (! $this->isVerified($user)) {
            throw new ApiException(
                'COMPANY_NOT_VERIFIED',
                'Ton entreprise doit être vérifiée avant d\'accéder aux candidats.',
                403,
            );
        }
    }
}
