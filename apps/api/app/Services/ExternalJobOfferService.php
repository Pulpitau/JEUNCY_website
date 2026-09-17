<?php

namespace App\Services;

use App\Enums\ContractType;
use App\Enums\ExternalJobOfferStatus;
use App\Exceptions\ApiException;
use App\Models\ExternalEmployerBlock;
use App\Models\ExternalJobOffer;
use App\Models\User;
use App\Services\Lba\ExternalOfferFilter;
use App\Services\Lba\LbaImportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Offres importees (La bonne alternance) : recherche publique, fiche, et
 * outils d'administration du filtre des ecoles.
 */
class ExternalJobOfferService
{
    // Recherche publique. Memes filtres que JobOfferService::searchPublished
    // pour que la page /offres applique une seule barre de recherche aux
    // deux listes. Les offres importees sont toutes de l'alternance : un
    // autre type de contrat demande renvoie une liste vide, pas une erreur.
    public function searchPublic(array $filters): LengthAwarePaginator
    {
        if (! empty($filters['contract_type']) && $filters['contract_type'] !== ContractType::ALTERNANCE->value) {
            return new LengthAwarePaginator([], 0, 12, 1);
        }

        $query = ExternalJobOffer::query()
            ->select(ExternalJobOffer::PUBLIC_COLUMNS)
            ->where('status', ExternalJobOfferStatus::ACTIVE)
            ->orderByDesc('published_at')
            ->orderByDesc('id');

        if (! empty($filters['city'])) {
            $query->where('city', 'like', '%'.$filters['city'].'%');
        }
        if (! empty($filters['work_mode'])) {
            $query->where('work_mode', $filters['work_mode']);
        }
        if (! empty($filters['department'])) {
            $query->where('department', $filters['department']);
        }
        if (! empty($filters['q'])) {
            $query->where(function (Builder $q) use ($filters) {
                $q->where('title', 'like', '%'.$filters['q'].'%')
                    ->orWhere('description', 'like', '%'.$filters['q'].'%')
                    ->orWhere('company_name', 'like', '%'.$filters['q'].'%');
            });
        }

        return $query->paginate(12)->withQueryString();
    }

    public function findPublic(int $id): ExternalJobOffer
    {
        $offer = ExternalJobOffer::query()
            ->select(ExternalJobOffer::PUBLIC_COLUMNS)
            ->where('status', ExternalJobOfferStatus::ACTIVE)
            ->find($id);

        if (! $offer) {
            throw new ApiException('EXTERNAL_JOB_OFFER_NOT_FOUND', "Cette offre n'est plus disponible.", 404);
        }

        return $offer;
    }

    // ------------------------------------------------------------------
    // Administration
    // ------------------------------------------------------------------

    public function adminList(array $filters): LengthAwarePaginator
    {
        $query = ExternalJobOffer::query()->orderByDesc('published_at')->orderByDesc('id');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['q'])) {
            $query->where(function (Builder $q) use ($filters) {
                $q->where('title', 'like', '%'.$filters['q'].'%')
                    ->orWhere('company_name', 'like', '%'.$filters['q'].'%');
            });
        }

        return $query->paginate(20)->withQueryString();
    }

    public function adminStats(): array
    {
        $byStatus = ExternalJobOffer::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $byReason = ExternalJobOffer::query()
            ->where('status', ExternalJobOfferStatus::EXCLUDED)
            ->selectRaw('exclusion_reason, count(*) as total')
            ->groupBy('exclusion_reason')
            ->orderByDesc('total')
            ->pluck('total', 'exclusion_reason')
            ->all();

        return [
            'actives' => (int) ($byStatus[ExternalJobOfferStatus::ACTIVE->value] ?? 0),
            'exclues' => (int) ($byStatus[ExternalJobOfferStatus::EXCLUDED->value] ?? 0),
            'exclues_par_raison' => $byReason,
            'employeurs_bloques' => ExternalEmployerBlock::query()->count(),
            'dernier_import' => LbaImportService::lastReport(),
            'departements' => (array) config('services.lba.departements'),
        ];
    }

    // Ecarte un employeur a partir d'une de ses offres, et retire aussitot
    // toutes ses offres visibles. Par SIRET quand on l'a, par nom sinon.
    // L'effet est immediat ET durable : l'import de la nuit suivante relit
    // les blocages (voir ExternalOfferFilter::loadManualBlocks).
    public function blockEmployerFromOffer(User $admin, ExternalJobOffer $offer, ?string $reason): ExternalEmployerBlock
    {
        $normalizedName = ExternalOfferFilter::normalize($offer->company_name);
        if ($offer->company_siret === null && $normalizedName === '') {
            throw new ApiException('EMPLOYER_NOT_IDENTIFIABLE', 'Cette offre ne porte ni SIRET ni nom d\'employeur exploitable.', 422);
        }

        $block = ExternalEmployerBlock::query()->firstOrCreate(
            $offer->company_siret !== null ? ['siret' => $offer->company_siret] : ['normalized_name' => $normalizedName],
            [
                'siret' => $offer->company_siret,
                'normalized_name' => $normalizedName !== '' ? $normalizedName : null,
                'display_name' => $offer->company_name ?? $offer->company_siret,
                'reason' => $reason ? mb_substr($reason, 0, 255) : null,
                'created_by' => $admin->id,
            ],
        );

        $affected = ExternalJobOffer::query()
            ->where('status', ExternalJobOfferStatus::ACTIVE)
            ->where(function (Builder $q) use ($offer, $normalizedName) {
                if ($offer->company_siret !== null) {
                    $q->orWhere('company_siret', $offer->company_siret);
                }
                if ($normalizedName !== '') {
                    $q->orWhere('company_name', $offer->company_name);
                }
            })
            ->update([
                'status' => ExternalJobOfferStatus::EXCLUDED->value,
                'exclusion_reason' => 'employeur bloque par un administrateur',
            ]);

        $block->setAttribute('offres_retirees', $affected);

        return $block;
    }

    // Retire UNE offre, sans toucher au reste de l'employeur : pour une
    // annonce de formation deguisee chez un employeur par ailleurs legitime.
    // Durable : LbaImportService reapplique l'exclusion apres chaque passe.
    public function excludeOffer(ExternalJobOffer $offer): ExternalJobOffer
    {
        $offer->update([
            'status' => ExternalJobOfferStatus::EXCLUDED,
            'exclusion_reason' => LbaImportService::ADMIN_EXCLUSION_REASON,
            'excluded_by_admin_at' => now(),
        ]);

        return $offer;
    }

    // Annule un retrait manuel. L'offre redevient visible tout de suite si
    // le filtre automatique ne la concerne pas ; sinon elle reste exclue avec
    // la raison du filtre a la prochaine passe.
    public function restoreOffer(ExternalJobOffer $offer): ExternalJobOffer
    {
        $offer->update([
            'status' => ExternalJobOfferStatus::ACTIVE,
            'exclusion_reason' => null,
            'excluded_by_admin_at' => null,
        ]);

        return $offer;
    }

    public function listBlocks(): array
    {
        return ExternalEmployerBlock::query()->orderByDesc('created_at')->get()->all();
    }

    // Lever un blocage ne remet rien en ligne tout seul : l'import de la
    // nuit suivante reevaluera les offres de cet employeur avec le filtre
    // normal. Un clic malheureux ne peut donc pas republier une ecole.
    public function removeBlock(ExternalEmployerBlock $block): void
    {
        $block->delete();
    }
}
