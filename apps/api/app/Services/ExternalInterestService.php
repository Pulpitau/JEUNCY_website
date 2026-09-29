<?php

namespace App\Services;

use App\Enums\ExternalInterestDecision;
use App\Exceptions\ApiException;
use App\Models\ExternalInterest;
use App\Models\ExternalJobOffer;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Gestes du candidat sur les offres PARTENAIRES (La bonne alternance).
 *
 * Pas de LIKE ni de match ici, a dessein : la candidature se fait sur le site
 * d'origine, personne du cote Jeuncy ne peut repondre. Le candidat « garde »
 * une offre pour y revenir, ou la « passe ».
 *
 * L'offre est RECOPIEE dans la ligne (employeur, intitule, ville, lien) :
 * l'import LBA supprime chaque nuit les offres absentes de l'export, et sans
 * cette copie la liste « Gardees » se viderait toute seule — le candidat
 * perdrait ce qu'il avait justement demande a garder.
 */
class ExternalInterestService
{
    public function __construct(
        private readonly CandidateProfileService $candidateProfileService,
    ) {}

    public function decide(User $user, ExternalJobOffer $offer, ExternalInterestDecision $decision): ExternalInterest
    {
        $profile = $this->candidateProfileService->requireProfile($user);

        return ExternalInterest::updateOrCreate(
            [
                'candidate_profile_id' => $profile->id,
                'external_job_offer_id' => $offer->id,
            ],
            [
                'decision' => $decision,
                'company_siret' => $offer->company_siret,
                'company_name' => $offer->company_name,
                'title' => $offer->title,
                'city' => $offer->city,
                'apply_url' => $offer->apply_url,
                'decided_at' => now(),
            ],
        );
    }

    /**
     * « C'est fait » : le candidat a postule sur le site d'origine. Jeuncy
     * n'en sait rien et ne peut pas le savoir — seul le candidat peut le
     * dire, et c'est ce qui fait sortir la ligne du haut de sa liste.
     */
    public function markDone(User $user, ExternalInterest $interest): ExternalInterest
    {
        $profile = $this->candidateProfileService->requireProfile($user);

        if ($interest->candidate_profile_id !== $profile->id) {
            throw new ApiException('FORBIDDEN', "Cette offre gardée ne t'appartient pas.", 403);
        }

        $interest->done_at = now();
        $interest->save();

        return $interest;
    }

    /**
     * Retirer une offre de la liste « Gardées ».
     *
     * LA LIGNE N'EST PAS SUPPRIMEE, elle passe a PASS. Trois raisons, dans
     * cet ordre :
     *
     * 1. Supprimer la ligne ferait REAPPARAITRE l'offre dans la pile des le
     *    lendemain (offresPartenaires n'ecarte que ce qui porte une
     *    decision). Le candidat a dit « je n'en veux plus » : la lui
     *    reservir serait lui desobeir.
     * 2. PASS porte un masquage de JOURS_MASQUAGE_PASS_PARTENAIRE jours, pas
     *    un bannissement. Au bout de deux mois l'offre peut revenir, ce qui
     *    est juste : elle aura change, ou le candidat aussi.
     * 3. `decided_at` est remis a MAINTENANT, sinon une offre gardee il y a
     *    trois mois serait immediatement re-eligible et reviendrait dans la
     *    pile le soir meme du retrait.
     *
     * A ne pas confondre avec undoLast(), qui efface le geste : annuler,
     * c'est dire « je me suis trompe, redonne-la moi » ; retirer, c'est dire
     * « je l'ai vue, je n'en veux plus ».
     */
    public function remove(User $user, ExternalInterest $interest): ExternalInterest
    {
        $profile = $this->candidateProfileService->requireProfile($user);

        if ($interest->candidate_profile_id !== $profile->id) {
            throw new ApiException('FORBIDDEN', "Cette offre gardée ne t'appartient pas.", 403);
        }

        if ($interest->decision !== ExternalInterestDecision::KEEP) {
            throw new ApiException('NOT_KEPT', "Cette offre n'est pas dans tes offres gardées.", 409);
        }

        $interest->decision = ExternalInterestDecision::PASS;
        $interest->decided_at = now();
        $interest->save();

        return $interest;
    }

    /**
     * @return Collection<int, ExternalInterest>
     */
    public function listKept(User $user): Collection
    {
        $profile = $this->candidateProfileService->requireProfile($user);

        return ExternalInterest::query()
            ->where('candidate_profile_id', $profile->id)
            ->where('decision', ExternalInterestDecision::KEEP->value)
            // Ce qui reste a faire d'abord : une offre deja traitee n'est
            // gardee que pour memoire.
            ->orderByRaw('CASE WHEN done_at IS NULL THEN 0 ELSE 1 END')
            ->orderByDesc('decided_at')
            ->get();
    }

    /**
     * @return array{undone: array{external_job_offer_id: ?int}}
     */
    public function undoLast(User $user): array
    {
        $profile = $this->candidateProfileService->requireProfile($user);

        $interest = ExternalInterest::query()
            ->where('candidate_profile_id', $profile->id)
            ->orderByDesc('decided_at')
            ->orderByDesc('id')
            ->first();

        if ($interest === null) {
            throw new ApiException('NOTHING_TO_UNDO', "Il n'y a rien à annuler.", 404);
        }

        if ($interest->decided_at === null
            || $interest->decided_at->lt(now()->subMinutes(InterestService::FENETRE_ANNULATION_MINUTES))) {
            throw new ApiException('UNDO_WINDOW_EXPIRED', 'Ce geste est trop ancien pour être annulé.', 409);
        }

        $offreId = $interest->external_job_offer_id;
        $interest->delete();

        return ['undone' => ['external_job_offer_id' => $offreId]];
    }
}
