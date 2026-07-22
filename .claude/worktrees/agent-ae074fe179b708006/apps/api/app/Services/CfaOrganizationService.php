<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\CfaOrganization;
use App\Models\User;

class CfaOrganizationService
{
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
