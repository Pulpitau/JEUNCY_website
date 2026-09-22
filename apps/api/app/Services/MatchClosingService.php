<?php

namespace App\Services;

use App\Enums\MatchClosedReason;
use App\Enums\NotificationType;
use App\Models\Application;
use App\Models\CandidateProfile;
use App\Models\JobOffer;
use App\Models\OfferInterest;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Fermeture des intérêts et des matchs (MOBILE.md §5).
 *
 * Une ligne offer_interests part en cascade avec son offre ou son profil.
 * Sans passage ici AVANT la suppression, l'autre partie verrait sa carte
 * disparaitre sans un mot : c'est exactement ce que le produit promet de ne
 * pas faire (« réponse garantie »). D'ou l'ordre impose a tous les
 * appelants — fermer, notifier, puis seulement supprimer.
 *
 * Le destinataire de la notification depend du POINT D'ENTREE, jamais de la
 * raison : c'est l'autre partie qui apprend la nouvelle, et « qui est
 * l'autre » se lit dans la methode appelee. closeForOffer part du cote
 * offre, donc previent le candidat ; closeForCandidateProfile et
 * closeForApplication partent du cote candidat, donc previennent
 * l'employeur. Faire dependre cela de la raison etait le defaut d'une
 * premiere version : ACCOUNT_DELETED arrive par les deux chemins (un
 * candidat qui part, une entreprise qui part) et aurait prevenu le mauvais.
 *
 * Idempotent partout (`whereNull('closed_at')`) : une commande planifiee qui
 * repasse sur la meme offre ne renotifie personne.
 */
class MatchClosingService
{
    /**
     * Offre archivée, supprimée, expirée, ou compte employeur supprimé.
     * Prévient les candidats matchés.
     *
     * @return int nombre de lignes fermées
     */
    public function closeForOffer(JobOffer $offer, MatchClosedReason $reason): int
    {
        $lignes = OfferInterest::query()
            ->open()
            ->where('job_offer_id', $offer->getKey())
            ->with('candidateProfile.user')
            ->get();

        return $this->fermer($lignes, $reason, function (OfferInterest $interest) use ($offer, $reason) {
            $this->previenirLeCandidat($interest, $offer, $reason);
        });
    }

    /**
     * Compte candidat supprimé : toutes ses lignes partent. Prévient les
     * employeurs matchés.
     *
     * @return int nombre de lignes fermées
     */
    public function closeForCandidateProfile(CandidateProfile $profile, MatchClosedReason $reason): int
    {
        $lignes = OfferInterest::query()
            ->open()
            ->where('candidate_profile_id', $profile->getKey())
            ->with(['jobOffer.company.user', 'jobOffer.cfaOrganization.user'])
            ->get();

        return $this->fermer($lignes, $reason, function (OfferInterest $interest) use ($profile, $reason) {
            $this->previenirLEmployeur($interest, $profile, $reason);
        });
    }

    /**
     * Dossier retiré par le candidat. Prévient l'employeur.
     *
     * La ligne est retrouvée par le couple (profil, offre) et non par
     * `application_id` : celui-ci n'est renseigné que si le match existait
     * déjà au moment du dossier, alors que le couple, lui, est toujours
     * juste. Chercher par l'identifiant de candidature aurait laissé
     * ouvertes exactement les lignes qu'un retrait doit fermer.
     *
     * @return int nombre de lignes fermées
     */
    public function closeForApplication(Application $application, MatchClosedReason $reason): int
    {
        $lignes = OfferInterest::query()
            ->open()
            ->where('candidate_profile_id', $application->candidate_profile_id)
            ->where('job_offer_id', $application->job_offer_id)
            ->with(['candidateProfile', 'jobOffer.company.user', 'jobOffer.cfaOrganization.user'])
            ->get();

        return $this->fermer($lignes, $reason, function (OfferInterest $interest) use ($reason) {
            $profile = $interest->candidateProfile;

            if ($profile !== null) {
                $this->previenirLEmployeur($interest, $profile, $reason);
            }
        });
    }

    /**
     * Ferme les lignes et ne notifie que celles qui etaient MATCHEES : un
     * interet a sens unique n'a jamais ete annonce a personne en face, il
     * n'y a donc rien a lui apprendre. Notifier quand meme reviendrait a
     * reveler apres coup un interet que le produit n'avait pas montre.
     *
     * @param  Collection<int, OfferInterest>  $lignes
     * @param  callable(OfferInterest): void  $notifier
     */
    private function fermer(Collection $lignes, MatchClosedReason $reason, callable $notifier): int
    {
        $ferme = 0;

        foreach ($lignes as $interest) {
            $etaitMatche = $interest->isMatched();

            $interest->closed_at = now();
            $interest->closed_reason = $reason;
            $interest->save();
            $ferme++;

            if ($etaitMatche) {
                $notifier($interest);
            }
        }

        return $ferme;
    }

    private function previenirLeCandidat(OfferInterest $interest, JobOffer $offer, MatchClosedReason $reason): void
    {
        $candidat = $interest->candidateProfile?->user;

        if ($candidat === null) {
            return;
        }

        $titre = $offer->title ?? 'cette offre';

        $message = match ($reason) {
            MatchClosedReason::OFFER_EXPIRED => "L'offre « {$titre} » est arrivée à échéance : le match est clos.",
            default => "L'offre « {$titre} » n'est plus disponible : le match est clos.",
        };

        $candidat->notifications()->create([
            'type' => NotificationType::MATCH_CLOSED,
            'message' => $message,
            'link' => '/mes-candidatures',
        ]);
    }

    private function previenirLEmployeur(OfferInterest $interest, CandidateProfile $profile, MatchClosedReason $reason): void
    {
        $offre = $interest->jobOffer;
        $employeur = $offre === null ? null : $this->proprietaire($offre);

        if ($employeur === null) {
            return;
        }

        $titre = $offre->title ?? 'ton offre';
        $qui = $this->libelleCandidat($profile);

        $message = match ($reason) {
            MatchClosedReason::APPLICATION_WITHDRAWN => "{$qui} a retiré son dossier pour « {$titre} ».",
            MatchClosedReason::ACCOUNT_DELETED => "{$qui} a supprimé son compte : le match sur « {$titre} » est clos.",
            default => "Le match sur « {$titre} » avec {$qui} est clos.",
        };

        $employeur->notifications()->create([
            'type' => NotificationType::MATCH_CLOSED,
            'message' => $message,
            'link' => '/mes-offres',
        ]);
    }

    /**
     * Compte derrière une offre, entreprise ou CFA.
     *
     * Résolu ici plutôt qu'en appelant JobOfferService::ownerUser : ce
     * service est appelé depuis JobOfferService lui-même (archivage,
     * suppression), et l'injecter en retour créerait une dépendance
     * circulaire pour six lignes.
     */
    private function proprietaire(JobOffer $offer): ?User
    {
        if ($offer->company_id) {
            return $offer->company?->user;
        }

        if ($offer->cfa_organization_id) {
            return $offer->cfaOrganization?->user;
        }

        return null;
    }

    /**
     * Prénom + initiale, jamais le nom complet : la notification suit la
     * meme regle d'exposition que la carte (CandidateCardPresenter). Un
     * message in-app est du texte, mais il est lu par le meme employeur.
     */
    private function libelleCandidat(CandidateProfile $profile): string
    {
        $prenom = trim((string) $profile->first_name);
        $nom = trim((string) $profile->last_name);

        if ($prenom === '') {
            return 'Un candidat';
        }

        return $nom === '' ? $prenom : $prenom.' '.mb_strtoupper(mb_substr($nom, 0, 1)).'.';
    }
}
