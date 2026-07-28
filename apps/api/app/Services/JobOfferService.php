<?php

namespace App\Services;

use App\Enums\JobOfferStatus;
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

        return $jobOffer->load('skills');
    }

    // Restreint au brouillon (comme le paiement, voir requireOwnedDraftOffer) :
    // une fois publiee (payee), une offre ne doit plus pouvoir changer de
    // contenu librement en gardant son statut/date de publication — le
    // frontend ne montre deja le bouton "Modifier" que pour une offre en
    // brouillon, cette restriction cote service ferme juste l'acces direct
    // par API. Archiver puis recreer reste possible pour changer le contenu.
    public function updateForUser(User $user, JobOffer $jobOffer, array $data): JobOffer
    {
        $jobOffer = $this->requireOwnedDraftOffer($user, $jobOffer);

        $skillNames = array_key_exists('skills', $data) ? $data['skills'] : null;
        unset($data['skills']);
        $jobOffer->update($data);

        if ($skillNames !== null) {
            $this->syncSkills($jobOffer, $skillNames);
        }

        return $jobOffer->load('skills');
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
        $jobOffer->update(['status' => JobOfferStatus::ARCHIVED]);

        return $jobOffer;
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

        if ($jobOffer->status !== JobOfferStatus::DRAFT && ! $payableFromTrialArchive) {
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

        $jobOffer->update([
            'status' => JobOfferStatus::PUBLISHED,
            'payment_status' => PaymentStatus::TRIAL,
            'published_at' => now(),
        ]);

        if ($isFirstTrialOffer) {
            $this->mailService->sendTrialStartedEmail($user->email, $organization->name, $this->priceLabelFor($jobOffer));
        }

        return $jobOffer;
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
