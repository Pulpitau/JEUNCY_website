<?php

namespace App\Services;

use App\Enums\ReportContext;
use App\Exceptions\ApiException;
use App\Models\CandidateProfile;
use App\Models\JobOffer;
use App\Models\OfferInterest;
use App\Models\Report;
use App\Models\User;

/**
 * Signalements (MOBILE.md §7). L'admin des signalements vient plus tard :
 * ici on ne fait que les recueillir, correctement adresses et sans doublon.
 *
 * LA CIBLE SE DESIGNE SANS user_id, comme pour les blocages : ni la carte
 * candidat (liste blanche du presenteur) ni la fiche d'une organisation
 * n'exposent l'identifiant du compte d'en face. Un client qui devrait
 * l'inventer finirait par signaler quelqu'un au hasard.
 */
class ReportService
{
    // Un meme signalement (meme cible, meme contexte) n'est enregistre
    // qu'une fois par 24 h : un doigt qui insiste ne doit pas noyer la file
    // de moderation, et l'equipe n'en apprendrait rien de plus.
    public const FENETRE_DOUBLON_HEURES = 24;

    public function __construct(private readonly JobOfferService $jobOfferService) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function report(User $user, array $data): Report
    {
        $context = ReportContext::from((string) $data['context']);
        [$cibleUserId, $offreId] = $this->resoudreCible($user, $context, $data);

        if ($cibleUserId === $user->id) {
            throw new ApiException('CANNOT_REPORT_SELF', 'Tu ne peux pas te signaler toi-même.', 400);
        }

        $deja = Report::query()
            ->where('reporter_user_id', $user->id)
            ->where('context', $context->value)
            ->when($cibleUserId === null, fn ($q) => $q->whereNull('reported_user_id'))
            ->when($cibleUserId !== null, fn ($q) => $q->where('reported_user_id', $cibleUserId))
            ->where('created_at', '>=', now()->subHours(self::FENETRE_DOUBLON_HEURES))
            ->exists();

        if ($deja) {
            throw new ApiException(
                'REPORT_ALREADY_SENT',
                'Ton signalement a déjà été transmis. Notre équipe le traite.',
                429,
            );
        }

        return Report::create([
            'reporter_user_id' => $user->id,
            'reported_user_id' => $cibleUserId,
            'job_offer_id' => $offreId,
            'context' => $context,
            'reason' => (string) $data['reason'],
            'details' => $data['details'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: ?int, 1: ?int}
     */
    private function resoudreCible(User $user, ReportContext $context, array $data): array
    {
        if (! empty($data['offer_interest_id'])) {
            return $this->cibleDuMatch($user, (int) $data['offer_interest_id']);
        }

        if (! empty($data['job_offer_id'])) {
            $offre = JobOffer::find((int) $data['job_offer_id']);

            if ($offre === null) {
                throw new ApiException('REPORT_TARGET_NOT_FOUND', "Impossible d'identifier ce qui est signalé.", 404);
            }

            return [$this->jobOfferService->ownerUser($offre)?->id, $offre->id];
        }

        if (! empty($data['candidate_profile_id'])) {
            $profile = CandidateProfile::find((int) $data['candidate_profile_id']);

            if ($profile === null) {
                throw new ApiException('REPORT_TARGET_NOT_FOUND', "Impossible d'identifier ce qui est signalé.", 404);
            }

            return [$profile->user_id, null];
        }

        throw new ApiException('REPORT_TARGET_NOT_FOUND', "Impossible d'identifier ce qui est signalé.", 404);
    }

    /**
     * Un match se signale par sa propre ligne : l'appelant n'a pas a savoir
     * qui est en face, le serveur le deduit de son role. La ligne doit etre
     * la SIENNE, sinon on signalerait pour le compte d'autrui.
     *
     * @return array{0: ?int, 1: ?int}
     */
    private function cibleDuMatch(User $user, int $offerInterestId): array
    {
        $interest = OfferInterest::with(['jobOffer', 'candidateProfile'])->find($offerInterestId);

        if ($interest === null) {
            throw new ApiException('REPORT_TARGET_NOT_FOUND', "Impossible d'identifier ce qui est signalé.", 404);
        }

        $profile = $user->candidateProfile;

        if ($profile !== null && $interest->candidate_profile_id === $profile->id) {
            $offre = $interest->jobOffer;

            return [$offre === null ? null : $this->jobOfferService->ownerUser($offre)?->id, $offre?->id];
        }

        $offre = $interest->jobOffer;
        if ($offre !== null && $this->jobOfferService->ownerUser($offre)?->id === $user->id) {
            return [$interest->candidateProfile?->user_id, $offre->id];
        }

        throw new ApiException('REPORT_TARGET_NOT_FOUND', "Impossible d'identifier ce qui est signalé.", 404);
    }
}
