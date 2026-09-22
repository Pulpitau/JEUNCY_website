<?php

namespace App\Services;

use App\Enums\ContractType;
use App\Enums\JobOfferStatus;
use App\Enums\MatchClosedReason;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Exceptions\ApiException;
use App\Models\CfaOrganization;
use App\Models\Company;
use App\Models\JobOffer;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class JobOfferService
{
    // Periode d'essai gratuite (entreprises et CFA) : 15 jours a compter de la
    // premiere (et unique) offre publiee gratuitement. Utilisable une seule
    // fois par entreprise/CFA (trial_started_at n'est jamais remis a null,
    // meme apres expiration — voir trialAvailable()). Quota reduit a 1 offre
    // le 2026-07-28 (demande explicite, etait 15 initialement).
    public const TRIAL_DURATION_DAYS = 15;

    public const TRIAL_MAX_OFFERS = 1;

    public function __construct(
        private readonly CompanyService $companyService,
        private readonly CfaOrganizationService $cfaOrganizationService,
        private readonly MailService $mailService,
        private readonly SubscriptionService $subscriptionService,
        private readonly JobOfferMatchService $matchService,
        private readonly GeocodingService $geocodingService,
    ) {}

    public function listOwn(User $user): Collection
    {
        return $this->ownOffersQuery($user)->with('skills')->latest()->get();
    }

    public function createForUser(User $user, array $data): JobOffer
    {
        $skillNames = $data['skills'] ?? null;
        unset($data['skills']);

        $jobOffer = JobOffer::create([
            ...$this->publisherForeignKey($user),
            ...$data,
            'status' => JobOfferStatus::DRAFT,
            'payment_status' => PaymentStatus::PENDING,
        ]);

        if ($skillNames !== null) {
            $this->syncSkills($jobOffer, $skillNames);
        }

        if ($jobOffer->postal_code !== null) {
            $this->geocodingService->apply($jobOffer, $jobOffer->postal_code, $jobOffer->city);
        }

        return $jobOffer->load('skills');
    }

    // Restreint au brouillon (comme le paiement, voir requireOwnedDraftOffer)
    // OU a une offre publiee GRATUITEMENT.
    //
    // La restriction historique protegeait ce qui avait ete paye : une offre
    // achetee ne doit pas changer de contenu en gardant sa date de
    // publication. Une offre gratuite n'a rien achete, et deux besoins
    // exigent qu'elle reste modifiable : l'offre express est publiee des sa
    // creation et doit etre completee ensuite (MOBILE.md §4.1), et la seule
    // offre publiee de production (IDA) doit pouvoir recevoir son code postal
    // pour entrer dans Decouvrir. Statut, published_at et
    // applications_unlocked_at sont conserves.
    public function updateForUser(User $user, JobOffer $jobOffer, array $data): JobOffer
    {
        $jobOffer = $this->requireOwnedEditableOffer($user, $jobOffer);

        $skillNames = array_key_exists('skills', $data) ? $data['skills'] : null;
        unset($data['skills']);

        $locationChanged = (array_key_exists('postal_code', $data) && $data['postal_code'] !== $jobOffer->postal_code)
            || (array_key_exists('city', $data) && $data['city'] !== $jobOffer->city);

        $jobOffer->update($data);

        if ($skillNames !== null) {
            $this->syncSkills($jobOffer, $skillNames);
        }

        if ($locationChanged) {
            $this->geocodingService->apply($jobOffer, $jobOffer->postal_code, $jobOffer->city);
        }

        return $jobOffer->load('skills');
    }

    // Offre modifiable : brouillon, ou publiee gratuitement (voir
    // updateForUser). Une offre payee, en essai ou par abonnement reste
    // fermee a la modification.
    private function requireOwnedEditableOffer(User $user, JobOffer $jobOffer): JobOffer
    {
        $jobOffer = $this->requireOwnedOffer($user, $jobOffer);

        $publishedForFree = $jobOffer->status === JobOfferStatus::PUBLISHED
            && $jobOffer->payment_status === PaymentStatus::FREE;

        if ($jobOffer->status !== JobOfferStatus::DRAFT && ! $publishedForFree) {
            throw new ApiException('JOB_OFFER_NOT_DRAFT', "Cette offre n'est plus en brouillon.", 409);
        }

        return $jobOffer;
    }

    // Meme pattern de dedoublonnage par nom que
    // CandidateProfileService::syncSkills, reutilise le meme referentiel Skill.
    private function syncSkills(JobOffer $jobOffer, array $names): void
    {
        $skillIds = collect($names)
            ->map(fn (string $name) => trim($name))
            ->filter()
            ->unique()
            ->map(fn (string $name) => Skill::firstOrCreate(['name' => $name])->id);

        $jobOffer->skills()->sync($skillIds);
    }

    public function archiveForUser(User $user, JobOffer $jobOffer): JobOffer
    {
        $jobOffer = $this->requireOwnedOffer($user, $jobOffer);

        // Une offre arrivee a echeance est DEJA hors ligne : l'archiver
        // n'ajoute rien et lui coute la possibilite d'etre remise en ligne,
        // puisqu'une offre archivee a la main n'est plus payable (voir
        // requirePayableOffer). Sur l'ecran meme ou on invite le client a
        // repayer, le bouton voisin detruirait son annonce sans le dire.
        // La garde vit ici et pas seulement dans le composant : l'API est
        // appelable directement.
        if ($jobOffer->status === JobOfferStatus::EXPIRED) {
            throw new ApiException(
                'JOB_OFFER_ALREADY_OFFLINE',
                "Cette offre n'est deja plus en ligne. Remets-la en ligne pour la rendre a nouveau visible.",
                409,
            );
        }

        $jobOffer->update(['status' => JobOfferStatus::ARCHIVED]);

        // Les matchs nes sur cette offre n'ont plus d'objet : on les ferme et
        // on previent l'autre partie. Un candidat qui a matche ne doit pas
        // attendre indefiniment un dossier a envoyer sur une offre retiree.
        $this->closeMatches($jobOffer, MatchClosedReason::OFFER_ARCHIVED);

        return $jobOffer;
    }

    // Suppression definitive (contrairement a archiveForUser, irreversible) :
    // demandee pour permettre a une entreprise/CFA de nettoyer une offre de
    // test ou obsolete plutot que de l'accumuler en "archivee". Les
    // candidatures et competences liees sont supprimees en cascade (voir
    // create_applications_table / create_job_offer_skills_table) ; un
    // paiement lie garde son historique (obligation comptable) mais perd sa
    // reference a l'offre (payments.job_offer_id passe a null, voir
    // create_payments_table) — comportement identique a la suppression de
    // compte RGPD (AccountService::deleteAccount).
    public function deleteForUser(User $user, JobOffer $jobOffer): void
    {
        $jobOffer = $this->requireOwnedOffer($user, $jobOffer);

        // AVANT le delete : offer_interests part en cascade avec l'offre, et
        // une ligne supprimee ne peut plus prevenir personne.
        $this->closeMatches($jobOffer, MatchClosedReason::OFFER_DELETED);

        $jobOffer->delete();
    }

    /**
     * Ferme les interets ouverts d'une offre (lot 1, MatchClosingService).
     *
     * Resolu par le conteneur a l'appel et non injecte au constructeur :
     * MatchClosingService n'a pas besoin de JobOfferService, mais l'inverse
     * est vrai, et une injection croisee au constructeur serait une
     * dependance circulaire pour deux appels.
     */
    private function closeMatches(JobOffer $jobOffer, MatchClosedReason $reason): void
    {
        app(MatchClosingService::class)->closeForOffer($jobOffer, $reason);
    }

    // Reutilise par PaymentService avant de creer une session de paiement : une
    // offre doit appartenir a l'utilisateur et etre encore en brouillon.
    public function requireOwnedDraftOffer(User $user, JobOffer $jobOffer): JobOffer
    {
        $jobOffer = $this->requireOwnedOffer($user, $jobOffer);
        if ($jobOffer->status !== JobOfferStatus::DRAFT) {
            throw new ApiException('JOB_OFFER_NOT_DRAFT', "Cette offre n'est plus en brouillon.", 409);
        }

        return $jobOffer;
    }

    // Reutilise par PaymentService : une offre payable est soit un brouillon
    // (parcours normal), soit une offre publiee via l'essai gratuit puis
    // archivee a l'expiration de celui-ci (voir ArchiveExpiredTrialOffers) —
    // l'entreprise peut alors payer pour la republier. Une offre archivee
    // manuellement (jamais liee a l'essai) reste volontairement non payable :
    // aucune demande n'a ete faite pour republier une offre payee archivee
    // par choix.
    public function requirePayableOffer(User $user, JobOffer $jobOffer): JobOffer
    {
        $jobOffer = $this->requireOwnedOffer($user, $jobOffer);
        $payableFromTrialArchive = $jobOffer->status === JobOfferStatus::ARCHIVED
            && $jobOffer->payment_status === PaymentStatus::TRIAL;

        // Une offre arrivee au bout de son mois de mise en ligne redevient
        // payable : c'est le renouvellement, devenu le parcours normal
        // depuis le 2026-09-10. Sans ce cas, une offre echue serait un
        // cul-de-sac — son proprietaire ne pourrait plus jamais la remettre
        // en ligne, ni donc payer. Une offre archivee A LA MAIN reste non
        // payable : son proprietaire l'a retiree volontairement.
        $payableFromExpiry = $jobOffer->status === JobOfferStatus::EXPIRED;

        if ($jobOffer->status !== JobOfferStatus::DRAFT && ! $payableFromTrialArchive && ! $payableFromExpiry) {
            throw new ApiException('JOB_OFFER_NOT_PAYABLE', 'Cette offre ne peut pas etre payee dans son etat actuel.', 409);
        }

        return $jobOffer;
    }

    // Ouvert aux entreprises et aux CFA : publie une offre en brouillon sans
    // paiement si l'essai gratuit est disponible. Demarre l'essai au premier
    // appel (trial_started_at), incremente le compteur d'offres a chaque appel
    // suivant. Envoie un email de bienvenue uniquement au tout premier appel
    // (demarrage), jamais pour les offres suivantes.
    public function publishViaTrialForUser(User $user, JobOffer $jobOffer): JobOffer
    {
        $jobOffer = $this->requireOwnedDraftOffer($user, $jobOffer);
        $organization = $this->trialHolder($user);

        if (! $this->trialAvailable($organization)) {
            throw new ApiException('TRIAL_NOT_AVAILABLE', "La periode d'essai gratuite n'est plus disponible pour ce compte.", 409);
        }

        $isFirstTrialOffer = $organization->trial_started_at === null;
        if ($isFirstTrialOffer) {
            $organization->trial_started_at = now();
        }
        $organization->trial_offers_count++;
        $organization->save();

        // L'essai gratuit inclut l'acces aux candidatures (contrairement au
        // paiement a l'offre desormais, voir PaymentService) : c'est tout
        // l'interet de l'essai. applications_unlocked_at n'est jamais remis a
        // null ensuite, meme apres l'archivage de l'offre a expiration de
        // l'essai (voir ArchiveExpiredTrialOffers) — les candidatures deja
        // recues restent consultables.
        $jobOffer->update([
            'status' => JobOfferStatus::PUBLISHED,
            'payment_status' => PaymentStatus::TRIAL,
            'published_at' => now(),
            // L'essai n'a pas d'echeance PORTEE PAR L'OFFRE : son retrait
            // depend de trial_started_at du compte (voir
            // ArchiveExpiredTrialOffers). Mis a null explicitement pour
            // qu'une offre passee du modele paye a l'essai ne traine pas
            // une vieille date qui la ferait retirer par la mauvaise
            // commande.
            'expires_at' => null,
            'applications_unlocked_at' => now(),
        ]);

        $this->matchService->notifyMatchingCandidates($jobOffer);

        if ($isFirstTrialOffer) {
            $this->mailService->sendTrialStartedEmail($user->email, $organization->name, $this->priceLabelFor($jobOffer));
        }

        return $jobOffer;
    }

    // Ouvert aux entreprises et aux CFA avec un abonnement actif (voir
    // SubscriptionService::hasActiveSubscription) : publie (ou republie, meme
    // regle que requirePayableOffer) une offre sans paiement a l'offre,
    // benefice central de l'abonnement ("publication illimitee", voir
    // Pricing.tsx). Contrairement a l'essai gratuit, applications_unlocked_at
    // n'est PAS fixe ici : l'acces aux candidatures d'un abonne reste verifie
    // dynamiquement (voir ApplicationService::listForOffer) et donc revocable
    // si l'abonnement est resilie/expire, alors que l'essai gratuit accorde un
    // acces definitif a cette offre precise.
    public function publishViaSubscriptionForUser(User $user, JobOffer $jobOffer): JobOffer
    {
        $jobOffer = $this->requirePayableOffer($user, $jobOffer);

        // hasActiveSubscription et NON hasPaidAccess, contrairement aux deux
        // autres gardes payantes : publier ici ecrit payment_status
        // SUBSCRIPTION sur l'offre, une donnee de facturation. Un compte ADMIN
        // n'a pas d'abonnement — le laisser passer inscrirait un revenu
        // fictif. (Inatteignable aujourd'hui, un admin ne possedant aucune
        // offre, mais la regle doit tenir si ca change.)
        if (! $this->subscriptionService->hasActiveSubscription($user)) {
            throw new ApiException('SUBSCRIPTION_NOT_ACTIVE', 'Aucun abonnement actif sur ce compte.', 409);
        }

        $jobOffer->update([
            'status' => JobOfferStatus::PUBLISHED,
            'payment_status' => PaymentStatus::SUBSCRIPTION,
            'published_at' => now(),
            // L'abonnement, c'est la publication illimitee : aucune
            // echeance. Indispensable ici, car requirePayableOffer accepte
            // desormais une offre EXPIRED — un abonne qui republie une
            // offre anciennement payee a l'unite heriterait sinon de son
            // echeance depassee et la verrait retiree des la nuit suivante.
            // Le client qui paie le plus cher serait le seul a perdre son
            // annonce.
            'expires_at' => null,
        ]);

        $this->matchService->notifyMatchingCandidates($jobOffer);

        return $jobOffer;
    }

    // Jeuncy gratuit pour les entreprises (decision du 2026-09-15, voir config
    // services.jeuncy.gratuit) : publie ou remet en ligne une offre sans
    // paiement, sans essai, sans echeance. Memes etats admis que le paiement
    // (requirePayableOffer) : brouillon, fin de mise en ligne, ou retrait de
    // fin d'essai — une offre archivee A LA MAIN reste hors de portee, son
    // proprietaire l'a retiree volontairement.
    //
    // Refuse hors mode gratuit : ce chemin ne doit jamais devenir une porte
    // derobee vers la publication si une grille tarifaire revient un jour.
    public function publishFreeForUser(User $user, JobOffer $jobOffer): JobOffer
    {
        if (! self::gratuit()) {
            throw new ApiException('FREE_PUBLICATION_DISABLED', "La publication gratuite n'est pas disponible.", 409);
        }

        $jobOffer = $this->requirePayableOffer($user, $jobOffer);
        $this->ensureLocated($jobOffer);

        $jobOffer->update([
            'status' => JobOfferStatus::PUBLISHED,
            'payment_status' => PaymentStatus::FREE,
            'published_at' => now(),
            // Pas d'echeance : une offre gratuite n'a rien a renouveler.
            // Mis a null explicitement pour la meme raison que l'abonnement :
            // une offre autrefois payee garderait sinon sa vieille date et
            // serait retiree la nuit suivante par ExpireJobOffers.
            'expires_at' => null,
            // Les candidatures sont incluses. Pose ici et pas seulement
            // deduit de hasPaidAccess() : si le mode gratuit est un jour
            // referme, les offres publiees pendant cette periode gardent
            // l'acces a ce qu'elles ont recu — on ne reprend pas ce qu'on
            // a donne.
            'applications_unlocked_at' => now(),
        ]);

        $this->matchService->notifyMatchingCandidates($jobOffer);

        return $jobOffer;
    }

    /**
     * Offre express (MOBILE.md §4.1) : intitule, contrat, commune + code
     * postal, secteur, rayon — creee ET publiee en une requete.
     *
     * Le deck de candidats ne doit jamais etre verrouille derriere le
     * formulaire long : une entreprise qui decouvre l'app doit pouvoir
     * publier en une minute, puis completer depuis « Mes offres » (ce que
     * requireOwnedEditableOffer autorise desormais pour une offre gratuite).
     *
     * Transaction : une offre creee mais non publiee serait un brouillon
     * fantome que personne n'a demande.
     */
    public function createExpressForUser(User $user, array $data): JobOffer
    {
        // Le geocodage AVANT d'ouvrir la transaction, jamais dedans : c'est
        // un appel reseau de 4 secondes au pire, et une transaction MySQL
        // tenue ouverte le temps qu'un service tiers reponde bloque ses
        // lignes pour rien. Cet appel remplit geocode_cache ; les deux
        // appels qui suivent (createForUser puis ensureLocated) y lisent la
        // reponse sans toucher au reseau.
        $this->geocodingService->geocode($data['postal_code'] ?? null, $data['city'] ?? null);

        return DB::transaction(function () use ($user, $data) {
            $jobOffer = $this->createForUser($user, [
                ...$data,
                'description' => $this->expressDescription($data),
            ]);

            return $this->publishFreeForUser($user, $jobOffer);
        });
    }

    private function expressDescription(array $data): string
    {
        $contract = $this->contractLabel(ContractType::tryFrom((string) ($data['contract_type'] ?? '')));
        $city = trim((string) ($data['city'] ?? ''));
        $postalCode = trim((string) ($data['postal_code'] ?? ''));

        return trim("{$data['title']} — {$contract} à {$city} ({$postalCode}). Description à compléter depuis Mes offres.");
    }

    private function contractLabel(?ContractType $contractType): string
    {
        return match ($contractType) {
            ContractType::ALTERNANCE => 'alternance',
            ContractType::SAISONNIER => 'emploi saisonnier',
            ContractType::BENEVOLAT => 'bénévolat',
            ContractType::JOB_ETUDIANT => 'job étudiant',
            ContractType::STAGE => 'stage',
            default => 'poste',
        };
    }

    /**
     * Une offre entre dans Decouvrir par son code postal : la commune seule
     * est ambigue (MOBILE.md §6). Repli sur celui de l'organisation, parce
     * qu'une PME publie presque toujours pour son propre etablissement ;
     * sinon on refuse la publication en disant quoi faire, plutot que de
     * publier une offre que personne ne verra jamais dans sa pile.
     */
    private function ensureLocated(JobOffer $jobOffer): void
    {
        if (blank($jobOffer->postal_code)) {
            $organization = $this->organizationOf($jobOffer);

            if (blank($organization?->postal_code)) {
                throw new ApiException(
                    'JOB_OFFER_POSTAL_CODE_REQUIRED',
                    'Indique le code postal du poste avant de publier.',
                    409,
                );
            }

            $jobOffer->postal_code = $organization->postal_code;
            if (blank($jobOffer->city)) {
                $jobOffer->city = $organization->city;
            }
            $jobOffer->save();
        }

        if (! $jobOffer->hasCoordinates()) {
            $this->geocodingService->apply($jobOffer, $jobOffer->postal_code, $jobOffer->city);
        }
    }

    private function organizationOf(JobOffer $jobOffer): Company|CfaOrganization|null
    {
        return $jobOffer->company_id !== null
            ? $jobOffer->company
            : $jobOffer->cfaOrganization;
    }

    public static function gratuit(): bool
    {
        return (bool) config('services.jeuncy.gratuit');
    }

    public function trialAvailable(Company|CfaOrganization $organization): bool
    {
        if ($organization->trial_offers_count >= self::TRIAL_MAX_OFFERS) {
            return false;
        }

        if ($organization->trial_started_at === null) {
            return true;
        }

        return now()->lessThan($organization->trial_started_at->addDays(self::TRIAL_DURATION_DAYS));
    }

    // Compte (Company ou CfaOrganization) qui porte l'essai gratuit pour cet
    // utilisateur — seuls COMPANY et CFA peuvent publier des offres (voir
    // publisherForeignKey), donc l'un des deux existe forcement ici.
    private function trialHolder(User $user): Company|CfaOrganization
    {
        return match ($user->role) {
            UserRole::COMPANY => $this->companyService->requireCompany($user),
            UserRole::CFA => $this->cfaOrganizationService->requireCfaOrganization($user),
            default => throw new ApiException('FORBIDDEN', "La periode d'essai gratuite est reservee aux entreprises et aux CFA.", 403),
        };
    }

    // Tarif de publication d'une offre (en centimes), different entreprise/CFA
    // (voir config/services.php) — reutilise par PaymentService pour la
    // session Stripe et par ArchiveExpiredTrialOffers pour l'email/notification
    // de fin d'essai.
    public function priceCentsFor(JobOffer $jobOffer): int
    {
        return $jobOffer->cfa_organization_id !== null
            ? config('services.stripe.cfa_offer_price_cents')
            : config('services.stripe.company_offer_price_cents');
    }

    public function priceLabelFor(JobOffer $jobOffer): string
    {
        return number_format($this->priceCentsFor($jobOffer) / 100, 2, ',', ' ').' €';
    }

    // Duree de mise en ligne achetee par un paiement a l'offre.
    public function publicationDays(): int
    {
        return max(1, (int) config('services.stripe.offer_publication_days'));
    }

    // Duree telle qu'on la dit a un client : « 1 mois » plutot que
    // « 30 jours ». Une seule source pour les libelles de Stripe, des
    // emails et des notifications — trois endroits qui doivent dire la
    // meme chose sous peine de faire douter celui qui paie.
    public function publicationDurationLabel(): string
    {
        $jours = $this->publicationDays();

        return $jours === 30 ? '1 mois' : "{$jours} jours";
    }

    public function requireOwnedOffer(User $user, JobOffer $jobOffer): JobOffer
    {
        if (! $this->isOwner($user, $jobOffer)) {
            throw new ApiException('FORBIDDEN', "Cette offre ne t'appartient pas.", 403);
        }

        return $jobOffer;
    }

    // Utilisateur (compte COMPANY ou CFA) proprietaire de l'offre, pour lui
    // notifier une nouvelle candidature (voir ApplicationService).
    public function ownerUser(JobOffer $jobOffer): ?User
    {
        if ($jobOffer->company_id) {
            return $jobOffer->company?->user;
        }
        if ($jobOffer->cfa_organization_id) {
            return $jobOffer->cfaOrganization?->user;
        }

        return null;
    }

    private function isOwner(User $user, JobOffer $jobOffer): bool
    {
        return match ($user->role) {
            UserRole::COMPANY => $jobOffer->company_id === $this->companyService->requireCompany($user)->id,
            UserRole::CFA => $jobOffer->cfa_organization_id === $this->cfaOrganizationService->requireCfaOrganization($user)->id,
            default => false,
        };
    }

    private function publisherForeignKey(User $user): array
    {
        return match ($user->role) {
            UserRole::COMPANY => ['company_id' => $this->companyService->requireCompany($user)->id],
            UserRole::CFA => ['cfa_organization_id' => $this->cfaOrganizationService->requireCfaOrganization($user)->id],
            default => throw new ApiException('FORBIDDEN', 'Seules les entreprises et les CFA peuvent publier des offres.', 403),
        };
    }

    // Recherche publique : uniquement les offres publiees, aucune authentification
    // requise (voir routes/api/job-offers.php).
    public function searchPublished(array $filters): LengthAwarePaginator
    {
        $query = JobOffer::query()
            ->where('status', JobOfferStatus::PUBLISHED)
            ->with(['company', 'cfaOrganization', 'skills'])
            ->orderByDesc('published_at');

        if (! empty($filters['contract_type'])) {
            $query->where('contract_type', $filters['contract_type']);
        }
        if (! empty($filters['city'])) {
            $query->where('city', 'like', '%'.$filters['city'].'%');
        }
        if (! empty($filters['work_mode'])) {
            $query->where('work_mode', $filters['work_mode']);
        }
        if (! empty($filters['q'])) {
            $query->where(function (Builder $q) use ($filters) {
                $q->where('title', 'like', '%'.$filters['q'].'%')
                    ->orWhere('description', 'like', '%'.$filters['q'].'%');
            });
        }

        return $query->paginate(12);
    }

    public function findPublished(int $id): JobOffer
    {
        $jobOffer = JobOffer::query()
            ->where('status', JobOfferStatus::PUBLISHED)
            ->with(['company', 'cfaOrganization', 'skills'])
            ->find($id);

        if (! $jobOffer) {
            throw new ApiException('JOB_OFFER_NOT_FOUND', "Cette offre n'existe pas ou n'est plus disponible.", 404);
        }

        return $jobOffer;
    }

    private function ownOffersQuery(User $user): Builder
    {
        return match ($user->role) {
            UserRole::COMPANY => JobOffer::query()->where('company_id', $this->companyService->requireCompany($user)->id),
            UserRole::CFA => JobOffer::query()->where('cfa_organization_id', $this->cfaOrganizationService->requireCfaOrganization($user)->id),
            default => throw new ApiException('FORBIDDEN', 'Seules les entreprises et les CFA peuvent consulter leurs offres.', 403),
        };
    }
}
