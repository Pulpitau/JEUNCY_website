<?php

namespace App\Services;

use App\Enums\JobOfferStatus;
use App\Exceptions\ApiException;
use App\Models\CfaOrganization;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CfaOrganizationService
{
    // Annuaire public : n'importe quel visiteur peut parcourir les CFA
    // inscrits, aucune authentification requise (voir routes/api/cfa-organizations.php).
    public function searchPublic(array $filters = []): LengthAwarePaginator
    {
        return CfaOrganization::query()
            ->when(
                $filters['name'] ?? null,
                fn ($query, $name) => $query->where('name', 'like', '%'.$name.'%'),
            )
            ->when(
                $filters['city'] ?? null,
                fn ($query, $city) => $query->where('city', 'like', '%'.$city.'%'),
            )
            ->when(
                $filters['diploma_level'] ?? null,
                fn ($query, $level) => $query->where('diploma_level', $level),
            )
            ->when(
                $filters['training_mode'] ?? null,
                fn ($query, $mode) => $query->where('training_mode', $mode),
            )
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

    // Voir CompanyService::withOwnerFields : les champs masques au public
    // (siret, etat d'essai) doivent revenir au proprietaire de la fiche.
    private function withOwnerFields(CfaOrganization $cfaOrganization): CfaOrganization
    {
        return $cfaOrganization->makeVisible(CfaOrganization::OWNER_VISIBLE);
    }

    public function getForUser(User $user): CfaOrganization
    {
        return $this->withOwnerFields($this->requireCfaOrganization($user));
    }

    public function createForUser(User $user, array $data): CfaOrganization
    {
        if ($user->cfaOrganization) {
            throw new ApiException('CFA_ORGANIZATION_ALREADY_EXISTS', 'Un profil CFA existe déjà pour ce compte.', 409);
        }

        return $this->withOwnerFields($user->cfaOrganization()->create($data));
    }

    public function updateForUser(User $user, array $data): CfaOrganization
    {
        $cfaOrganization = $this->requireCfaOrganization($user);
        $cfaOrganization->update($data);

        return $this->withOwnerFields($cfaOrganization);
    }

    public function requireCfaOrganization(User $user): CfaOrganization
    {
        $cfaOrganization = $user->cfaOrganization;
        if (! $cfaOrganization) {
            throw new ApiException('CFA_ORGANIZATION_NOT_FOUND', "Aucun profil CFA n'existe encore pour ce compte.", 404);
        }

        return $cfaOrganization;
    }

    public function uploadLogo(User $user, UploadedFile $file): CfaOrganization
    {
        $cfaOrganization = $this->requireCfaOrganization($user);

        if ($cfaOrganization->logo_url) {
            $this->deleteStoredLogo($cfaOrganization->logo_url);
        }

        $filename = $cfaOrganization->id.'-'.Str::uuid().'.'.$file->extension();
        $path = $file->storeAs('cfa-logos', $filename, 'public');
        $cfaOrganization->update(['logo_url' => Storage::disk('public')->url($path)]);

        return $this->withOwnerFields($cfaOrganization);
    }

    public function removeLogo(User $user): CfaOrganization
    {
        $cfaOrganization = $this->requireCfaOrganization($user);

        if ($cfaOrganization->logo_url) {
            $this->deleteStoredLogo($cfaOrganization->logo_url);
            $cfaOrganization->update(['logo_url' => null]);
        }

        return $this->withOwnerFields($cfaOrganization);
    }

    private function deleteStoredLogo(string $logoUrl): void
    {
        $base = rtrim(Storage::disk('public')->url(''), '/').'/';
        $relativePath = Str::startsWith($logoUrl, $base) ? substr($logoUrl, strlen($base)) : $logoUrl;
        Storage::disk('public')->delete($relativePath);
    }
}
