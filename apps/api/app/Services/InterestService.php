<?php

namespace App\Services;

use App\Enums\InterestDecision;
use App\Enums\JobOfferStatus;
use App\Enums\UserRole;
use App\Exceptions\ApiException;
use App\Models\CandidateProfile;
use App\Models\JobOffer;
use App\Models\OfferInterest;
use App\Models\User;
use App\Support\MatchPerimeter;
use Illuminate\Support\Carbon;

/**
 * Les gestes de Decouvrir : « Ca m'interesse » (LIKE), « Passer » (PASS) et
 * l'annulation du dernier geste.
 *
 * Les gardes vivent ici, l'ecriture dans MatchService : ce service decide qui
 * a le droit de poser un geste, l'autre decide ce que deux gestes produisent.
 */
class InterestService
{
    // Fenetre d'annulation du dernier geste. Assez pour rattraper un pouce
    // qui derape, trop court pour re-parcourir la pile a l'envers.
    public const FENETRE_ANNULATION_MINUTES = 5;

    // Fenetre theorique d'annulation d'un match. En pratique inatteignable :
    // sans worker, la notification et l'email partent DANS l'appel qui cree
    // le match, et *_notified_at sont poses dans la foulee — un match est
    // donc toujours « deja notifie » quand l'annulation arrive. La fenetre
    // reste ecrite pour le jour ou une file d'attente differera les envois ;
    // la condition « ou l'autre partie a ete notifiee » est celle qui
    // s'applique aujourd'hui, et c'est elle qui protege le destinataire
    // d'un message annonce puis retire.
    public const FENETRE_ANNULATION_MATCH_SECONDES = 60;

    // Un lot de PASS est un rattrapage de gestes hors-ligne, pas un outil de
    // balayage : 50 couvre deux piles pleines.
    public const LOT_MAX = 50;

    public function __construct(
        private readonly CandidateProfileService $candidateProfileService,
        private readonly JobOfferService $jobOfferService,
        private readonly CompanyVerificationService $verificationService,
        private readonly BlockService $blockService,
        private readonly DiscoverService $discoverService,
        private readonly MatchService $matchService,
    ) {}

    /**
     * « Ca m'interesse », des deux cotes.
     *
     * @return array{interest: array<string, mixed>, matched: bool}
     */
    public function like(User $user, JobOffer $offer, ?CandidateProfile $target = null): array
    {
        if ($user->role === UserRole::CANDIDATE) {
            $profile = $this->candidateProfileService->requireProfile($user);
            $this->assertOffrePubliee($offer);
            $this->assertNonBloque($user, $this->jobOfferService->ownerUser($offer)?->id);

            // Le quota ne se consulte QUE si le geste est nouveau : un client
            // mobile rejoue volontiers sa requete sur un reseau instable, et
            // un rejeu du vingtieme « Ca m'interesse » recevrait sinon un 429
            // pour un geste deja compte. record() est idempotent, cette garde
            // ne doit pas l'empecher de l'etre.
            if (! $this->decisionDejaPosee($profile->id, $offer->id, MatchService::SIDE_CANDIDATE)) {
                $this->assertQuotaCandidat($profile);
            }

            $interest = $this->matchService->record(
                $profile->id,
                $offer->id,
                MatchService::SIDE_CANDIDATE,
                InterestDecision::LIKE,
            );

            return $this->projeter($interest, MatchService::SIDE_CANDIDATE);
        }

        $this->assertOffreEmployeur($user, $offer);

        if ($target === null) {
            throw new ApiException('CANDIDATE_NOT_ELIGIBLE', "Ce candidat n'est plus disponible.", 409);
        }

        $this->assertNonBloque($user, $target->user_id);

        // Meme raison que cote candidat, aggravee ici : le deck employeur
        // SORT une carte des qu'une decision est posee dessus (voir
        // DiscoverService::candidatesQuery). Consulter l'eligibilite sur un
        // rejeu repondrait donc « ce candidat n'est plus disponible » a un
        // employeur dont le geste a pourtant abouti — et lui ferait perdre la
        // carte a l'ecran. On laisse record() trancher : identique = rien,
        // contraire = INTEREST_ALREADY_DECIDED, ce qui est la verite.
        if (! $this->decisionDejaPosee($target->id, $offer->id, MatchService::SIDE_EMPLOYER)) {
            $this->assertCibleEligible($user, $offer, $target);
            $this->assertQuotaEmployeur($offer);
        }

        $interest = $this->matchService->record(
            $target->id,
            $offer->id,
            MatchService::SIDE_EMPLOYER,
            InterestDecision::LIKE,
        );

        return $this->projeter($interest, MatchService::SIDE_EMPLOYER);
    }

    /**
     * « Passer », par lot.
     *
     * Ni perimetre ni eligibilite ici : un PASS ne revele rien et ne cree
     * rien de visible par l'autre partie. Il n'ecrase jamais une decision
     * deja posee — un lot rejoue apres un retour de reseau ne doit pas
     * effacer le « Ca m'interesse » pose entre-temps.
     *
     * @param  list<int>  $jobOfferIds
     * @param  list<int>  $candidateProfileIds
     */
    public function passBatch(User $user, array $jobOfferIds, array $candidateProfileIds = []): int
    {
        if ($user->role === UserRole::CANDIDATE) {
            $profile = $this->candidateProfileService->requireProfile($user);
            $couples = array_map(
                fn (int $offreId) => [$profile->id, $offreId],
                array_slice(array_values(array_unique($jobOfferIds)), 0, self::LOT_MAX),
            );

            return $this->poserLesPass($couples, MatchService::SIDE_CANDIDATE);
        }

        $offreId = $jobOfferIds[0] ?? null;
        if ($offreId === null) {
            return 0;
        }

        $offer = JobOffer::findOrFail($offreId);
        // Sans cette garde, un employeur poserait des PASS sur l'offre d'un
        // autre et ferait disparaitre des candidats de la pile d'un
        // concurrent.
        $this->jobOfferService->requireOwnedOffer($user, $offer);
        $this->verificationService->requireVerified($user);

        $couples = array_map(
            fn (int $profilId) => [$profilId, $offer->id],
            array_slice(array_values(array_unique($candidateProfileIds)), 0, self::LOT_MAX),
        );

        return $this->poserLesPass($couples, MatchService::SIDE_EMPLOYER);
    }

    /**
     * Annule le dernier geste de l'appelant.
     *
     * @return array{undone: array{job_offer_id: int, candidate_profile_id: int}}
     */
    public function undoLast(User $user): array
    {
        [$interest, $side, $decideAt] = $this->dernierGeste($user);

        if ($interest === null) {
            throw new ApiException('NOTHING_TO_UNDO', "Il n'y a rien à annuler.", 404);
        }

        if ($decideAt === null || $decideAt->lt(now()->subMinutes(self::FENETRE_ANNULATION_MINUTES))) {
            throw new ApiException(
                'UNDO_WINDOW_EXPIRED',
                'Ce geste est trop ancien pour être annulé.',
                409,
            );
        }

        if ($interest->application_id !== null) {
            throw new ApiException(
                'APPLICATION_ATTACHED',
                'Retire ta candidature pour annuler.',
                409,
            );
        }

        if ($interest->matched_at !== null && $this->matchDejaAnnonce($interest, $side)) {
            throw new ApiException(
                'MATCH_ALREADY_NOTIFIED',
                "L'autre partie a déjà été prévenue de ce match.",
                409,
            );
        }

        $couple = [
            'job_offer_id' => $interest->job_offer_id,
            'candidate_profile_id' => $interest->candidate_profile_id,
        ];

        $this->matchService->clearDecision($interest, $side);

        return ['undone' => $couple];
    }

    /**
     * Un match est annulable tant que l'autre partie n'a rien recu ET que la
     * fenetre de 60 s court encore. Les deux conditions, pas l'une ou
     * l'autre : un match vieux de 10 s mais deja annonce serait retire a
     * quelqu'un qui l'a deja vu.
     */
    private function matchDejaAnnonce(OfferInterest $interest, string $side): bool
    {
        $notificationAutrePartie = $side === MatchService::SIDE_CANDIDATE
            ? $interest->employer_notified_at
            : $interest->candidate_notified_at;

        return $notificationAutrePartie !== null
            || $interest->matched_at->lt(now()->subSeconds(self::FENETRE_ANNULATION_MATCH_SECONDES));
    }

    /**
     * @return array{0: ?OfferInterest, 1: string, 2: ?Carbon}
     */
    private function dernierGeste(User $user): array
    {
        if ($user->role === UserRole::CANDIDATE) {
            $profile = $this->candidateProfileService->requireProfile($user);

            $interest = OfferInterest::query()
                ->where('candidate_profile_id', $profile->id)
                ->whereNotNull('candidate_decided_at')
                ->whereNull('closed_at')
                ->orderByDesc('candidate_decided_at')
                ->first();

            return [$interest, MatchService::SIDE_CANDIDATE, $interest?->candidate_decided_at];
        }

        $this->verificationService->requireVerified($user);
        $organisation = $this->verificationService->organizationFor($user);
        $colonne = $user->role === UserRole::CFA ? 'cfa_organization_id' : 'company_id';

        $interest = OfferInterest::query()
            ->whereHas('jobOffer', fn ($q) => $q->where($colonne, $organisation?->id ?? 0))
            ->whereNotNull('employer_decided_at')
            ->whereNull('closed_at')
            ->orderByDesc('employer_decided_at')
            ->first();

        return [$interest, MatchService::SIDE_EMPLOYER, $interest?->employer_decided_at];
    }

    /**
     * @param  list<array{0: int, 1: int}>  $couples
     */
    private function poserLesPass(array $couples, string $side): int
    {
        $champDecision = $side === MatchService::SIDE_CANDIDATE ? 'candidate_decision' : 'employer_decision';
        $champDate = $side === MatchService::SIDE_CANDIDATE ? 'candidate_decided_at' : 'employer_decided_at';

        $poses = 0;

        foreach ($couples as [$profilId, $offreId]) {
            $interest = OfferInterest::query()
                ->where('candidate_profile_id', $profilId)
                ->where('job_offer_id', $offreId)
                ->first();

            if ($interest !== null && ($interest->{$champDecision} !== null || $interest->closed_at !== null)) {
                continue;
            }

            $interest ??= new OfferInterest([
                'candidate_profile_id' => $profilId,
                'job_offer_id' => $offreId,
            ]);

            $interest->{$champDecision} = InterestDecision::PASS;
            $interest->{$champDate} = now();
            $interest->save();

            $poses++;
        }

        return $poses;
    }

    /**
     * Une decision de ce cote existe-t-elle deja sur ce couple ? Sert a
     * distinguer un geste NOUVEAU (qui passe les gardes) d'un REJEU (qui doit
     * retomber sur l'idempotence de MatchService::record).
     */
    private function decisionDejaPosee(int $candidateProfileId, int $jobOfferId, string $side): bool
    {
        $champ = $side === MatchService::SIDE_CANDIDATE ? 'candidate_decision' : 'employer_decision';

        return OfferInterest::query()
            ->where('candidate_profile_id', $candidateProfileId)
            ->where('job_offer_id', $jobOfferId)
            ->whereNotNull($champ)
            ->exists();
    }

    private function assertOffrePubliee(JobOffer $offer): void
    {
        if ($offer->status !== JobOfferStatus::PUBLISHED) {
            throw new ApiException('JOB_OFFER_NOT_PUBLISHED', "Cette offre n'est plus disponible.", 409);
        }
    }

    private function assertOffreEmployeur(User $user, JobOffer $offer): void
    {
        $this->jobOfferService->requireOwnedOffer($user, $offer);
        $this->verificationService->requireVerified($user);

        // Une offre non publiee n'a pas de page publique : la notification
        // « X s'interesse a ton profil » pointerait vers /offres/{id}, que
        // PublicJobOfferController refuse (JOB_OFFER_NOT_FOUND). Un employeur
        // ne doit pas non plus parcourir des cartes de candidats depuis un
        // brouillon que personne ne peut lire.
        if ($offer->status !== JobOfferStatus::PUBLISHED) {
            throw new ApiException(
                'JOB_OFFER_NOT_PUBLISHED',
                'Publie cette offre avant de contacter des candidats.',
                409,
            );
        }

        if (! MatchPerimeter::isOpen($offer->postal_code)) {
            throw new ApiException(
                'MATCH_NOT_OPEN_HERE',
                "Découvrir n'est pas encore ouvert dans ce département.",
                403,
            );
        }
    }

    private function assertNonBloque(User $user, ?int $autreUserId): void
    {
        if ($autreUserId !== null && in_array($autreUserId, $this->blockService->blockedUserIdsFor($user), true)) {
            throw new ApiException('USER_BLOCKED', "Cette mise en relation n'est pas possible.", 403);
        }
    }

    // Un LIKE employeur ne peut viser qu'une carte que le deck lui aurait
    // montree : sans cette verification, un identifiant devine suffirait a
    // s'interesser a un mineur hors de toute portee declaree.
    private function assertCibleEligible(User $user, JobOffer $offer, CandidateProfile $target): void
    {
        $eligible = $this->discoverService->candidatesQuery($user, $offer)
            ->where('candidate_profiles.id', $target->id)
            ->exists();

        if (! $eligible) {
            throw new ApiException('CANDIDATE_NOT_ELIGIBLE', "Ce candidat n'est plus disponible.", 409);
        }
    }

    private function assertQuotaCandidat(CandidateProfile $profile): void
    {
        $quota = $this->discoverService->quotaFor($profile);

        if ($quota['active'] && $quota['used'] >= $quota['limit']) {
            throw new ApiException(
                'INTEREST_QUOTA_REACHED',
                'Tu as atteint tes 20 « Ça m\'intéresse » du jour. Reviens demain.',
                429,
            );
        }
    }

    private function assertQuotaEmployeur(JobOffer $offer): void
    {
        $quota = $this->discoverService->employerQuotaFor($offer);

        if ($quota['used'] >= $quota['limit']) {
            throw new ApiException(
                'INTEREST_QUOTA_REACHED',
                'Tu as atteint tes 30 « Ça m\'intéresse » du jour pour cette offre. Reviens demain.',
                429,
            );
        }
    }

    /**
     * Projection volontairement partielle : serialiser OfferInterest tel quel
     * renverrait la decision de l'AUTRE partie (employer_decision: "PASS" au
     * candidat, ou l'inverse), que rien dans le produit ne montre. Un
     * matched_at non nul dit tout ce qu'il y a a dire.
     *
     * @return array{interest: array<string, mixed>, matched: bool}
     */
    private function projeter(OfferInterest $interest, string $side): array
    {
        $estCandidat = $side === MatchService::SIDE_CANDIDATE;

        return [
            'interest' => [
                'id' => $interest->id,
                'job_offer_id' => $interest->job_offer_id,
                'candidate_profile_id' => $interest->candidate_profile_id,
                'decision' => $estCandidat ? $interest->candidate_decision : $interest->employer_decision,
                'decided_at' => $estCandidat ? $interest->candidate_decided_at : $interest->employer_decided_at,
                'matched_at' => $interest->matched_at,
                'application_id' => $interest->application_id,
            ],
            'matched' => $interest->matched_at !== null,
        ];
    }
}
