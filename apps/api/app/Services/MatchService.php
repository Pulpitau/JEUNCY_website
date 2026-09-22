<?php

namespace App\Services;

use App\Enums\InterestDecision;
use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Exceptions\ApiException;
use App\Models\Application;
use App\Models\CandidateProfile;
use App\Models\CfaOrganization;
use App\Models\JobOffer;
use App\Models\OfferInterest;
use App\Models\User;
use App\Presenters\CandidateCardPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Ecriture d'une decision sur un couple candidat / offre, et naissance du
 * match quand les deux ont dit oui (MOBILE.md §5).
 *
 * POURQUOI UN VERROU. Les deux parties peuvent cliquer a la meme seconde.
 * Sans lockForUpdate, deux requetes concurrentes lisent chacune une ligne
 * sans match, posent chacune matched_at, et les deux envoient l'annonce : le
 * candidat recoit deux emails pour un seul match. Le verrou de ligne fait de
 * « les deux ont dit oui » un evenement unique.
 */
class MatchService
{
    public const SIDE_CANDIDATE = 'candidate';

    public const SIDE_EMPLOYER = 'employer';

    public function __construct(
        private readonly CandidateProfileService $candidateProfileService,
        private readonly JobOfferService $jobOfferService,
        private readonly CompanyVerificationService $verificationService,
        private readonly BlockService $blockService,
        private readonly CandidateCardPresenter $presenter,
        private readonly MailService $mailService,
    ) {}

    /**
     * Pose la decision d'un cote et renvoie la ligne a jour.
     *
     * $notify a false : la decision est enregistree et les horodatages de
     * notification sont poses, mais rien n'est envoye. Un seul appelant s'en
     * sert, ApplicationService : quand le LIKE du candidat accompagne un
     * dossier, la notification NEW_APPLICATION existante annonce deja la
     * meme chose, et un NEW_MATCH en plus la dirait deux fois.
     */
    public function record(
        int $candidateProfileId,
        int $jobOfferId,
        string $side,
        InterestDecision $decision,
        bool $notify = true,
    ): OfferInterest {
        [$interest, $nouveauMatch, $nouvelleDecision] = DB::transaction(function () use ($candidateProfileId, $jobOfferId, $side, $decision) {
            $interest = OfferInterest::query()
                ->where('candidate_profile_id', $candidateProfileId)
                ->where('job_offer_id', $jobOfferId)
                ->lockForUpdate()
                ->first();

            if ($interest === null) {
                $interest = new OfferInterest([
                    'candidate_profile_id' => $candidateProfileId,
                    'job_offer_id' => $jobOfferId,
                ]);
            }

            if ($interest->closed_at !== null) {
                throw new ApiException('INTEREST_CLOSED', "Cette offre n'est plus disponible.", 409);
            }

            $champDecision = $side === self::SIDE_CANDIDATE ? 'candidate_decision' : 'employer_decision';
            $champDate = $side === self::SIDE_CANDIDATE ? 'candidate_decided_at' : 'employer_decided_at';

            $deja = $interest->{$champDecision};
            if ($deja !== null) {
                // Repeter le meme geste ne fait rien (un client mobile rejoue
                // volontiers une requete sur un reseau instable) ; se
                // contredire est refuse.
                if ($deja === $decision) {
                    return [$interest, false, false];
                }

                throw new ApiException(
                    'INTEREST_ALREADY_DECIDED',
                    'Tu as déjà répondu sur cette offre.',
                    409,
                );
            }

            $interest->{$champDecision} = $decision;
            $interest->{$champDate} = now();

            $nouveauMatch = $interest->candidate_decision === InterestDecision::LIKE
                && $interest->employer_decision === InterestDecision::LIKE
                && $interest->matched_at === null;

            $application = null;
            if ($nouveauMatch) {
                $interest->matched_at = now();

                // Un dossier deja envoye est rattache au match : sans ca, le
                // candidat serait invite a envoyer ce qu'il a deja envoye.
                $application = Application::query()
                    ->where('candidate_profile_id', $candidateProfileId)
                    ->where('job_offer_id', $jobOfferId)
                    ->first();
                $interest->application_id = $application?->id;
            }

            $interest->save();

            if ($application !== null && $application->interest_id === null) {
                $application->interest_id = $interest->id;
                $application->save();
            }

            return [$interest, $nouveauMatch, true];
        });

        if ($nouveauMatch) {
            $this->annoncerLeMatch($interest, $notify);

            return $interest;
        }

        if ($nouvelleDecision && $side === self::SIDE_EMPLOYER && $decision === InterestDecision::LIKE) {
            $this->annoncerInteretEmployeur($interest, $notify);
        }

        return $interest;
    }

    /**
     * Efface la decision d'un cote SANS toucher a celle de l'autre.
     *
     * Reserve a deux appelants : InterestService::undoLast (annulation du
     * dernier geste) et ApplicationService::applyForUser (un PASS anterieur
     * cede devant un dossier — postuler est un geste plus fort que passer).
     * Une ligne devenue vide est supprimee : elle n'a plus rien a dire, et
     * la laisser bloquerait le candidat par la contrainte d'unicite.
     */
    public function clearDecision(OfferInterest $interest, string $side): void
    {
        $champDecision = $side === self::SIDE_CANDIDATE ? 'candidate_decision' : 'employer_decision';
        $champDate = $side === self::SIDE_CANDIDATE ? 'candidate_decided_at' : 'employer_decided_at';

        $interest->{$champDecision} = null;
        $interest->{$champDate} = null;
        $interest->matched_at = null;
        $interest->candidate_notified_at = null;
        $interest->employer_notified_at = null;

        if ($interest->candidate_decision === null
            && $interest->employer_decision === null
            && $interest->application_id === null) {
            $interest->delete();

            return;
        }

        $interest->save();
    }

    /**
     * Matchs ouverts de l'appelant.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function listForUser(User $user): Collection
    {
        return $this->matchesQuery($user)
            ->latest('matched_at')
            ->get()
            ->map(fn (OfferInterest $interest) => $this->presenterMatch($user, $interest));
    }

    /**
     * @return array<string, mixed>
     */
    public function findForUser(User $user, OfferInterest $interest): array
    {
        $ligne = $this->matchesQuery($user)->whereKey($interest->getKey())->first();

        // 404 et non 403, a dessein : repondre « interdit » sur la ligne d'un
        // autre confirmerait qu'elle existe.
        if ($ligne === null) {
            throw new ApiException('MATCH_NOT_FOUND', "Ce match n'existe pas ou n'est plus disponible.", 404);
        }

        return $this->presenterMatch($user, $ligne);
    }

    /**
     * @return Builder<OfferInterest>
     */
    private function matchesQuery(User $user): Builder
    {
        $bloques = $this->blockService->blockedUserIdsFor($user);

        $query = OfferInterest::query()
            ->matched()
            ->open()
            ->with(['jobOffer.company', 'jobOffer.cfaOrganization', 'application']);

        if ($user->role === UserRole::CANDIDATE) {
            $profile = $this->candidateProfileService->requireProfile($user);

            return $query
                ->where('candidate_profile_id', $profile->id)
                ->when($bloques !== [], fn (Builder $q) => $q->whereHas('jobOffer', fn ($o) => $o
                    ->whereDoesntHave('company', fn ($c) => $c->whereIn('user_id', $bloques))
                    ->whereDoesntHave('cfaOrganization', fn ($c) => $c->whereIn('user_id', $bloques))));
        }

        // Une entreprise passee REJECTED a une re-verification ne doit plus
        // lire de carte candidat, y compris celles de ses matchs deja noues.
        $this->verificationService->requireVerified($user);

        $organisation = $this->verificationService->organizationFor($user);
        $colonne = $organisation instanceof CfaOrganization ? 'cfa_organization_id' : 'company_id';
        $id = $organisation?->id ?? 0;

        return $query
            ->whereHas('jobOffer', fn ($o) => $o->where($colonne, $id))
            ->with(['candidateProfile.skills:id,name', 'candidateProfile.software:id,name',
                'candidateProfile.languages', 'candidateProfile.educations', 'candidateProfile.experiences'])
            ->when($bloques !== [], fn (Builder $q) => $q->whereHas('candidateProfile', fn ($p) => $p->whereNotIn('user_id', $bloques)));
    }

    /**
     * @return array<string, mixed>
     */
    private function presenterMatch(User $user, OfferInterest $interest): array
    {
        $commun = [
            'id' => $interest->id,
            'matched_at' => $interest->matched_at,
            'status' => $interest->application_id === null ? 'AWAITING_APPLICATION' : 'APPLICATION_SENT',
        ];

        if ($user->role === UserRole::CANDIDATE) {
            return array_merge($commun, [
                'job_offer' => $interest->jobOffer,
                // Le candidat n'a pas besoin du dossier complet : il l'a
                // ecrit. Seul son avancement l'interesse.
                'application' => $interest->application === null ? null : [
                    'id' => $interest->application->id,
                    'status' => $interest->application->status,
                    'responded_at' => $interest->application->responded_at,
                ],
            ]);
        }

        $offre = $interest->jobOffer;

        return array_merge($commun, [
            'job_offer' => $offre === null ? null : ['id' => $offre->id, 'title' => $offre->title],
            'candidate' => $interest->candidateProfile === null
                ? null
                : $this->presenter->present($interest->candidateProfile, $offre),
            // Tant que le dossier n'est pas envoye, l'employeur ne voit que
            // la carte : le match ouvre la conversation, il ne livre pas le
            // CV ni les coordonnees.
            'application' => $interest->application?->load(['candidateProfile.user:id,email', 'generatedCv']),
        ]);
    }

    /**
     * Notification + email aux deux parties, HORS transaction : un envoi
     * lent ou en echec ne doit pas tenir un verrou de ligne ouvert.
     *
     * Les horodatages *_notified_at sont poses meme quand $notify est faux :
     * ils disent « l'autre partie sait », ce qui est vrai des lors qu'une
     * notification de candidature est partie a sa place.
     */
    private function annoncerLeMatch(OfferInterest $interest, bool $notify): void
    {
        $interest->loadMissing(['jobOffer.company', 'jobOffer.cfaOrganization', 'candidateProfile.user']);

        $offre = $interest->jobOffer;
        $profile = $interest->candidateProfile;
        $candidat = $profile?->user;
        $employeur = $offre === null ? null : $this->jobOfferService->ownerUser($offre);
        $dossierEnvoye = $interest->application_id !== null;

        if ($notify && $offre !== null) {
            $organisation = $this->nomOrganisation($offre);

            $candidat?->notifications()->create([
                'type' => NotificationType::NEW_MATCH,
                'message' => $dossierEnvoye
                    ? "{$organisation} s'intéresse à ta candidature pour « {$offre->title} »."
                    : "{$organisation} veut te parler de « {$offre->title} ». Envoie ton dossier !",
                'link' => '/mes-candidatures',
            ]);

            $employeur?->notifications()->create([
                'type' => NotificationType::NEW_MATCH,
                'message' => $dossierEnvoye
                    ? "{$this->libelleCandidat($profile)} a déjà envoyé son dossier pour « {$offre->title} »."
                    : "{$this->libelleCandidat($profile)} a répondu à ton intérêt pour « {$offre->title} ».",
                'link' => '/mes-offres',
            ]);

            $base = rtrim((string) config('app.frontend_url'), '/');

            if ($candidat?->email) {
                $this->envoyerSansCasserLeParcours(
                    fn () => $this->mailService->sendNewMatchEmail(
                        to: $candidat->email,
                        counterpartLabel: $organisation,
                        offerTitle: $offre->title,
                        applicationSent: $dossierEnvoye,
                        url: $base.'/mes-candidatures',
                    ),
                    "annonce de match a {$candidat->email}",
                );
            }

            if ($employeur?->email) {
                $this->envoyerSansCasserLeParcours(
                    fn () => $this->mailService->sendNewMatchEmail(
                        to: $employeur->email,
                        counterpartLabel: $this->libelleCandidat($profile),
                        offerTitle: $offre->title,
                        applicationSent: $dossierEnvoye,
                        url: $base.'/mes-offres',
                    ),
                    "annonce de match a {$employeur->email}",
                );
            }
        }

        $interest->candidate_notified_at = now();
        $interest->employer_notified_at = now();
        $interest->save();
    }

    /**
     * LIKE employeur seul : le candidat est prevenu in-app, sans email.
     * L'offre remonte en tete de sa pile (employer_interested), ce qui est
     * le vrai canal — la notification n'est qu'un rappel.
     */
    private function annoncerInteretEmployeur(OfferInterest $interest, bool $notify): void
    {
        // Le candidat a ecarte l'offre : on ne la lui remet pas sous les
        // yeux. Le deck employeur ne devrait d'ailleurs plus l'avoir
        // propose (voir DiscoverService::candidatesQuery).
        if (! $notify || $interest->candidate_decision === InterestDecision::PASS) {
            return;
        }

        $interest->loadMissing(['jobOffer.company', 'jobOffer.cfaOrganization', 'candidateProfile.user']);

        $offre = $interest->jobOffer;
        $candidat = $interest->candidateProfile?->user;

        if ($offre === null || $candidat === null) {
            return;
        }

        $candidat->notifications()->create([
            'type' => NotificationType::INTEREST_RECEIVED,
            'message' => "{$this->nomOrganisation($offre)} s'intéresse à ton profil pour « {$offre->title} ».",
            'link' => '/offres/'.$offre->id,
        ]);
    }

    private function nomOrganisation(JobOffer $offre): string
    {
        return $offre->company?->name ?? $offre->cfaOrganization?->name ?? 'Un recruteur';
    }

    // Prenom + initiale, jamais le nom complet : c'est exactement ce que la
    // carte montre, et un email est plus facile a faire suivre qu'un ecran.
    private function libelleCandidat(?CandidateProfile $profile): string
    {
        if ($profile === null) {
            return 'Un candidat';
        }

        $initiale = mb_strtoupper(mb_substr((string) $profile->last_name, 0, 1));

        return trim($profile->first_name.' '.($initiale !== '' ? $initiale.'.' : ''));
    }

    // Meme filet que ApplicationService : le match est deja enregistre quand
    // on arrive ici, un echec d'email ne doit pas le faire disparaitre.
    private function envoyerSansCasserLeParcours(callable $envoi, string $contexte): void
    {
        try {
            $envoi();
        } catch (\Throwable $e) {
            Log::error("Echec d'envoi ({$contexte}) : {$e->getMessage()}");
        }
    }
}
