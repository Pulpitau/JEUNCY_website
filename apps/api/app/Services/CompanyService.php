<?php

namespace App\Services;

use App\Enums\JobOfferStatus;
use App\Exceptions\ApiException;
use App\Models\Company;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class CompanyService
{
    // Annuaire public : n'importe quel visiteur peut parcourir les entreprises
    // inscrites, aucune authentification requise (voir routes/api/companies.php).
    public function searchPublic(?string $city = null): LengthAwarePaginator
    {
        return Company::query()
            ->when($city, fn ($query) => $query->where('city', 'like', '%'.$city.'%'))
            ->orderBy('name')
            ->paginate(12);
    }

    public function findPublic(int $id): Company
    {
        $company = Company::query()
            ->with(['jobOffers' => fn ($query) => $query->where('status', JobOfferStatus::PUBLISHED)])
            ->find($id);
        if (! $company) {
            throw new ApiException('COMPANY_NOT_FOUND', "Cette entreprise n'existe pas.", 404);
        }

        return $company;
    }

    public function getForUser(User $user): Company
    {
        return $this->requireCompany($user);
    }

    public function createForUser(User $user, array $data): Company
    {
        if ($user->company) {
            throw new ApiException('COMPANY_ALREADY_EXISTS', 'Un profil entreprise existe déjà pour ce compte.', 409);
        }

        return $user->company()->create($data);
    }

    public function updateForUser(User $user, array $data): Company
    {
        $company = $this->requireCompany($user);
        $company->update($data);

        return $company;
    }

    public function requireCompany(User $user): Company
    {
        $company = $user->company;
        if (! $company) {
            throw new ApiException('COMPANY_NOT_FOUND', "Aucun profil entreprise n'existe encore pour ce compte.", 404);
        }

        return $company;
    }
}
