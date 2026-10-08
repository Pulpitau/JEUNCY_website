<?php

namespace App\Services;

use App\Enums\JobOfferStatus;
use App\Enums\NotificationType;
use App\Enums\VerificationStatus;
use App\Exceptions\ApiException;
use App\Models\CandidateProfile;
use App\Models\CandidateRecommendation;
use App\Models\Company;
use App\Models\JobOffer;
use App\Models\User;
use App\Presenters\CandidateCardPresenter;

/**
 * Espace CFA, chantier 2 de la feuille de route (2026-10-07) : le CFA
 * declare ses entreprises partenaires (toujours un compte Jeuncy VERIFIE,
 * jamais un envoi a l'aveugle) puis leur recommande un de ses propres
 * candidats (ceux rattaches via le badge « JEUNCY x ecole »,
 * candidate_profiles.cfa_organization_id) pour une de leurs offres
 * publiees.
 *
 * Une recommandation ne cree JAMAIS d'interet employeur a la place de
 * l'entreprise (decision de Pierre, 2026-10-08) : seulement une
 * notification et un lien vers la carte. L'entreprise reste libre de liker
 * ou non via le « Ca m'interesse » normal (POST interests, InterestService),
 * inchange.
 */
class CfaPartnershipService
{
    public function __construct(
        private readonly CfaOrganizationService $cfaOrganizationService,
        private readonly JobOfferService $jobOfferService,
        private readonly DiscoverService $discoverService,
        private readonly CandidateCardPresenter $presenter,
    ) {}

    /**
     * @return list<array{id: int, name: string, city: ?string}>
     */
    public function searchVerifiedCompanies(string $query): array
    {
        return Company::query()
            ->where('verification_status', VerificationStatus::VERIFIED)
            ->where('name', 'like', '%'.$query.'%')
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'city'])
            ->map(fn (Company $c) => ['id' => $c->id, 'name' => $c->name, 'city' => $c->city])
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, city: ?string}>
     */
    public function listPartnerCompanies(User $user): array
    {
        $cfa = $this->cfaOrganizationService->requireCfaOrganization($user);

        return $cfa->partnerCompanies()
            ->orderBy('name')
            ->get(['companies.id', 'companies.name', 'companies.city'])
            ->map(fn (Company $c) => ['id' => $c->id, 'name' => $c->name, 'city' => $c->city])
            ->all();
    }

    public function addPartnerCompany(User $user, int $companyId): void
    {
        $cfa = $this->cfaOrganizationService->requireCfaOrganization($user);
        $company = Company::find($companyId);

        if ($company === null || ! $company->isVerified()) {
            throw new ApiException(
                'COMPANY_NOT_VERIFIED',
                "Cette entreprise n'est pas encore un compte Jeuncy vérifié.",
                422,
            );
        }

        $cfa->partnerCompanies()->syncWithoutDetaching([$company->id]);
    }

    public function removePartnerCompany(User $user, int $companyId): void
    {
        $cfa = $this->cfaOrganizationService->requireCfaOrganization($user);
        $cfa->partnerCompanies()->detach($companyId);
    }

    /**
     * Les candidats que l'admin a rattaches a ce CFA — jamais au-dela : le
     * presenteur reste la seule porte d'exposition d'un profil candidat,
     * meme ici.
     *
     * @return list<array<string, mixed>>
     */
    public function listMyCandidates(User $user): array
    {
        $cfa = $this->cfaOrganizationService->requireCfaOrganization($user);

        return CandidateProfile::query()
            ->where('cfa_organization_id', $cfa->id)
            ->whereHas('user', fn ($q) => $q->notDeleted())
            ->with(['skills:id,name', 'software:id,name', 'languages', 'educations', 'experiences'])
            ->get()
            ->map(fn (CandidateProfile $profile) => $this->presenter->present($profile))
            ->all();
    }

    /**
     * @return list<array{id: int, title: string}>
     */
    public function listPartnerOffers(User $user, int $companyId): array
    {
        $company = $this->requirePartnerCompany($user, $companyId);

        return $company->jobOffers()
            ->where('status', JobOfferStatus::PUBLISHED)
            ->orderByDesc('published_at')
            ->get(['id', 'title'])
            ->map(fn (JobOffer $offer) => ['id' => $offer->id, 'title' => $offer->title])
            ->all();
    }

    public function recommend(User $user, int $candidateProfileId, int $jobOfferId): CandidateRecommendation
    {
        $cfa = $this->cfaOrganizationService->requireCfaOrganization($user);

        $candidate = CandidateProfile::find($candidateProfileId);
        if ($candidate === null || $candidate->cfa_organization_id !== $cfa->id) {
            throw new ApiException('CANDIDATE_NOT_YOURS', "Ce candidat n'est pas rattaché à ton CFA.", 403);
        }

        $offer = JobOffer::find($jobOfferId);
        if ($offer === null || $offer->company_id === null
            || ! $cfa->partnerCompanies()->where('companies.id', $offer->company_id)->exists()) {
            throw new ApiException(
                'OFFER_NOT_PARTNER',
                "Cette offre n'appartient pas à une entreprise partenaire de ton CFA.",
                403,
            );
        }
        if ($offer->status !== JobOfferStatus::PUBLISHED) {
            throw new ApiException('JOB_OFFER_NOT_PUBLISHED', "Cette offre n'est plus publiée.", 409);
        }

        $ownerUser = $this->jobOfferService->ownerUser($offer);

        // Meme garde que le "Ca m'interesse" employeur normal
        // (InterestService::assertCibleEligible) : recommander un candidat
        // que l'offre ne pourrait de toute facon jamais liker (hors rayon,
        // mauvais type de contrat...) serait une impasse pour l'employeur.
        $eligible = $ownerUser !== null && $this->discoverService->candidatesQuery($ownerUser, $offer)
            ->where('candidate_profiles.id', $candidate->id)
            ->exists();
        if (! $eligible) {
            throw new ApiException(
                'CANDIDATE_NOT_ELIGIBLE',
                'Ce candidat ne correspond pas aux critères de cette offre.',
                409,
            );
        }

        // firstOrCreate, pas create : un second clic sur le meme couple ne
        // doit pas spammer l'employeur d'une notification par tentative.
        $recommendation = CandidateRecommendation::firstOrCreate(
            ['candidate_profile_id' => $candidate->id, 'job_offer_id' => $offer->id],
            ['cfa_organization_id' => $cfa->id],
        );

        if ($recommendation->wasRecentlyCreated) {
            $ownerUser->notifications()->create([
                'type' => NotificationType::CANDIDATE_RECOMMENDED,
                'message' => "{$cfa->name} te recommande un candidat pour ton offre « {$offer->title} ».",
                'link' => "/offres/{$offer->id}/recommandations",
            ]);
        }

        return $recommendation;
    }

    /**
     * Les candidats que des CFA partenaires ont recommandes pour les offres
     * de l'entreprise connectee.
     *
     * @return list<array<string, mixed>>
     */
    public function listReceivedRecommendations(User $user): array
    {
        $company = $user->company;
        if ($company === null) {
            return [];
        }

        return CandidateRecommendation::query()
            ->whereHas('jobOffer', fn ($q) => $q->where('company_id', $company->id))
            ->with([
                'jobOffer:id,title',
                'cfaOrganization:id,name',
                'candidateProfile.skills:id,name',
                'candidateProfile.software:id,name',
                'candidateProfile.languages',
                'candidateProfile.educations',
                'candidateProfile.experiences',
            ])
            ->latest()
            ->get()
            ->map(fn (CandidateRecommendation $recommendation) => [
                'id' => $recommendation->id,
                'job_offer' => ['id' => $recommendation->jobOffer->id, 'title' => $recommendation->jobOffer->title],
                'recommended_by' => $recommendation->cfaOrganization->name,
                'candidate' => $this->presenter->present($recommendation->candidateProfile, $recommendation->jobOffer),
            ])
            ->all();
    }

    private function requirePartnerCompany(User $user, int $companyId): Company
    {
        $cfa = $this->cfaOrganizationService->requireCfaOrganization($user);

        $company = $cfa->partnerCompanies()->where('companies.id', $companyId)->first();
        if ($company === null) {
            throw new ApiException(
                'COMPANY_NOT_PARTNER',
                "Cette entreprise n'est pas une partenaire déclarée de ton CFA.",
                403,
            );
        }

        return $company;
    }
}
