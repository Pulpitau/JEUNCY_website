<?php

namespace App\Services;

use App\Enums\JobOfferStatus;
use App\Exceptions\ApiException;
use App\Models\CfaOrganization;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class CfaOrganizationService
{
    // Annuaire public : n'importe quel visiteur peut parcourir les CFA
    // inscrits, aucune authentification requise (voir routes/api/cfa-organizations.php).
    public function searchPublic(?string $city = null): LengthAwarePaginator
    {
        return CfaOrganization::query()
            ->when($city, fn ($query) => $query->where('city', 'like', '%'.$city.'%'))
            ->orderBy('name')
            ->paginate(12);
    }

    public function findPublic(int $id): CfaOrganization
    {
        $cfaOrganization = CfaOrganization::query()
            ->with(['jobOffers' => fn ($query) => $query->where('status', JobOfferStatus::PUBLISHED)])
            ->find($id);
        if (! $cfaOrganization) {
            throw new ApiException('CFA_ORGANIZATION_NOT_FOUND', "Ce CFA n'existe pas.", 404);
        }

        return $cfaOrganization;
    }

    public function getForUser(User $user): CfaOrganization
    {
        return $this->requireCfaOrganization($user);
    }

    public function createForUser(User $user, array $data): CfaOrganization
    {
        if ($user->cfaOrganization) {
            throw new ApiException('CFA_ORGANIZATION_ALREADY_EXISTS', 'Un profil CFA existe déjà pour ce compte.', 409);
        }

        return $user->cfaOrganization()->create($data);
    }

    public function updateForUser(User $user, array $data): CfaOrganization
    {
        $cfaOrganization = $this->requireCfaOrganization($user);
        $cfaOrganization->update($data);

        return $cfaOrganization;
    }

    public function requireCfaOrganization(User $user): CfaOrganization
    {
        $cfaOrganization = $user->cfaOrganization;
        if (! $cfaOrganization) {
            throw new ApiException('CFA_ORGANIZATION_NOT_FOUND', "Aucun profil CFA n'existe encore pour ce compte.", 404);
        }

        return $cfaOrganization;
    }
}
