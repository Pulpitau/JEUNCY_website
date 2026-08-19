<?php

namespace App\Services;

use App\Enums\JobOfferStatus;
use App\Exceptions\ApiException;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CompanyService
{
    // Annuaire public : n'importe quel visiteur peut parcourir les entreprises
    // inscrites, aucune authentification requise (voir routes/api/companies.php).
    public function searchPublic(array $filters = []): LengthAwarePaginator
    {
        return Company::query()
            // Fiches retirees de l'annuaire par leur proprietaire (voir la
            // migration add_is_public_to_organizations_tables). Leurs offres
            // publiees restent visibles : c'est ce pour quoi elles ont paye.
            ->where('is_public', true)
            ->when(
                $filters['name'] ?? null,
                fn ($query, $name) => $query->where('name', 'like', '%'.$name.'%'),
            )
            ->when(
                $filters['city'] ?? null,
                fn ($query, $city) => $query->where('city', 'like', '%'.$city.'%'),
            )
            ->when(
                $filters['work_mode'] ?? null,
                fn ($query, $workMode) => $query->where('work_mode', $workMode),
            )
            // Filtre par type de contrat des offres publiees de l'entreprise
            // (pas un champ direct de Company, contrairement a work_mode).
            ->when(
                $filters['contract_type'] ?? null,
                fn ($query, $contractType) => $query->whereHas(
                    'jobOffers',
                    fn ($jobOfferQuery) => $jobOfferQuery
                        ->where('status', JobOfferStatus::PUBLISHED)
                        ->where('contract_type', $contractType),
                ),
            )
            ->orderBy('name')
            ->paginate(12);
    }

    public function findPublic(int $id): Company
    {
        // is_public filtre aussi l'acces direct par URL, et pas seulement
        // l'annuaire : une fiche masquee qui reste consultable en devinant son
        // id ne serait pas masquee. Son proprietaire continue de la voir et de
        // la modifier depuis "Mon entreprise".
        $company = Company::query()
            ->where('is_public', true)
            ->with(['jobOffers' => fn ($query) => $query->where('status', JobOfferStatus::PUBLISHED)])
            ->find($id);
        if (! $company) {
            throw new ApiException('COMPANY_NOT_FOUND', "Cette entreprise n'existe pas.", 404);
        }

        return $company;
    }

    // Company::$hidden masque siret et l'etat d'essai pour le public. Le
    // proprietaire, lui, doit les recuperer : son formulaire reaffiche le
    // SIRET saisi et son tableau de bord a besoin de l'essai restant. Toute
    // methode qui renvoie la fiche a SON proprietaire passe par ici.
    private function withOwnerFields(Company $company): Company
    {
        return $company->makeVisible(Company::OWNER_VISIBLE);
    }

    public function getForUser(User $user): Company
    {
        return $this->withOwnerFields($this->requireCompany($user));
    }

    public function createForUser(User $user, array $data): Company
    {
        if ($user->company) {
            throw new ApiException('COMPANY_ALREADY_EXISTS', 'Un profil entreprise existe déjà pour ce compte.', 409);
        }

        return $this->withOwnerFields($user->company()->create($data));
    }

    public function updateForUser(User $user, array $data): Company
    {
        $company = $this->requireCompany($user);
        $company->update($data);

        return $this->withOwnerFields($company);
    }

    public function requireCompany(User $user): Company
    {
        $company = $user->company;
        if (! $company) {
            throw new ApiException('COMPANY_NOT_FOUND', "Aucun profil entreprise n'existe encore pour ce compte.", 404);
        }

        return $company;
    }

    // Rassure le candidat sur les offres publiees (voir CLAUDE.md) : un logo
    // visible sur les cartes/pages d'offres rend l'annonce moins "vide" et
    // plus credible, meme pattern que la photo de profil candidat.
    public function uploadLogo(User $user, UploadedFile $file): Company
    {
        $company = $this->requireCompany($user);

        if ($company->logo_url) {
            $this->deleteStoredLogo($company->logo_url);
        }

        $filename = $company->id.'-'.Str::uuid().'.'.$file->extension();
        $path = $file->storeAs('company-logos', $filename, 'public');
        $company->update(['logo_url' => Storage::disk('public')->url($path)]);

        return $this->withOwnerFields($company);
    }

    public function removeLogo(User $user): Company
    {
        $company = $this->requireCompany($user);

        if ($company->logo_url) {
            $this->deleteStoredLogo($company->logo_url);
            $company->update(['logo_url' => null]);
        }

        return $this->withOwnerFields($company);
    }

    private function deleteStoredLogo(string $logoUrl): void
    {
        $base = rtrim(Storage::disk('public')->url(''), '/').'/';
        $relativePath = Str::startsWith($logoUrl, $base) ? substr($logoUrl, strlen($base)) : $logoUrl;
        Storage::disk('public')->delete($relativePath);
    }
}
