<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Company;
use App\Models\User;

class CompanyService
{
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
