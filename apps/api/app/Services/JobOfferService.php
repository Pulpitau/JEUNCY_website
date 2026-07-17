<?php

namespace App\Services;

use App\Enums\JobOfferStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Exceptions\ApiException;
use App\Models\JobOffer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class JobOfferService
{
    public function __construct(
        private readonly CompanyService $companyService,
        private readonly CfaOrganizationService $cfaOrganizationService,
    ) {}

    public function listOwn(User $user): Collection
    {
        return $this->ownOffersQuery($user)->latest()->get();
    }

    public function createForUser(User $user, array $data): JobOffer
    {
        return JobOffer::create([
            ...$this->publisherForeignKey($user),
            ...$data,
            'status' => JobOfferStatus::DRAFT,
            'payment_status' => PaymentStatus::PENDING,
        ]);
    }

    public function updateForUser(User $user, JobOffer $jobOffer, array $data): JobOffer
    {
        $jobOffer = $this->requireOwnedOffer($user, $jobOffer);
        $jobOffer->update($data);

        return $jobOffer;
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

    public function requireOwnedOffer(User $user, JobOffer $jobOffer): JobOffer
    {
        if (! $this->isOwner($user, $jobOffer)) {
            throw new ApiException('FORBIDDEN', "Cette offre ne t'appartient pas.", 403);
        }

        return $jobOffer;
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
            ->with(['company', 'cfaOrganization'])
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
            ->with(['company', 'cfaOrganization'])
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
