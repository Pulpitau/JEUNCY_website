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
    public function __construct(
        private readonly CompanyVerificationService $verificationService,
        private readonly GeocodingService $geocodingService,
    ) {}

    // Annuaire public : n'importe quel visiteur peut parcourir les CFA
    // inscrits, aucune authentification requise (voir routes/api/cfa-organizations.php).
    public function searchPublic(array $filters = []): LengthAwarePaginator
    {
        return CfaOrganization::query()
            // Voir CompanyService::searchPublic : meme regle de visibilite.
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
        // Voir CompanyService::findPublic : masquer doit aussi fermer l'acces
        // direct par id, sinon ce n'est pas masquer.
        $cfaOrganization = CfaOrganization::query()
            ->where('is_public', true)
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

        $cfaOrganization = $user->cfaOrganization()->create($data);

        // Meme ordre que CompanyService : la fiche d'abord, le reseau
        // ensuite. La verification d'un CFA ne regarde pas son code NAF
        // d'enseignement (voir CompanyVerificationService) — c'est justement
        // son metier.
        $this->geocodingService->apply($cfaOrganization, $cfaOrganization->postal_code, $cfaOrganization->city);
        $this->verificationService->verify($cfaOrganization);

        return $this->withOwnerFields($cfaOrganization);
    }

    public function updateForUser(User $user, array $data): CfaOrganization
    {
        $cfaOrganization = $this->requireCfaOrganization($user);

        // Pas de garde SIRET_REQUIRED ici, contrairement a l'entreprise :
        // l'inscription CFA est fermee (AuthService::assertRoleOpenForRegistration),
        // les seules fiches CFA existantes sont anterieures a cette colonne et
        // exiger un SIRET a la modification bloquerait l'ecole partenaire sur
        // un changement de logo. Le SIRET reste exige a la CREATION.
        $siretChanged = array_key_exists('siret', $data) && $data['siret'] !== $cfaOrganization->siret;
        $locationChanged = (array_key_exists('postal_code', $data) && $data['postal_code'] !== $cfaOrganization->postal_code)
            || (array_key_exists('city', $data) && $data['city'] !== $cfaOrganization->city);

        $cfaOrganization->update($data);

        if ($locationChanged) {
            $this->geocodingService->apply($cfaOrganization, $cfaOrganization->postal_code, $cfaOrganization->city);
        }

        if ($siretChanged || ! $cfaOrganization->isVerified()) {
            $this->verificationService->verify($cfaOrganization);
        }

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
