<?php

namespace App\Http\Controllers;

use App\Enums\ExternalJobOfferStatus;
use App\Enums\JobOfferStatus;
use App\Http\Requests\JobOffer\SearchJobOffersRequest;
use App\Models\ExternalJobOffer;
use App\Models\JobOffer;
use App\Services\JobOfferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class PublicJobOfferController extends Controller
{
    public function __construct(private readonly JobOfferService $service) {}

    public function index(SearchJobOffersRequest $request): JsonResponse
    {
        return response()->json($this->service->searchPublished($request->validated()));
    }

    public function show(int $jobOffer): JsonResponse
    {
        return response()->json($this->service->findPublished($jobOffer));
    }

    // Nombre d'offres en ligne, offres Jeuncy et offres partenaires (La
    // bonne alternance) confondues : affiche en page d'accueil. Le VRAI
    // chiffre, en direct, jamais arrondi vers le haut — un visiteur qui
    // compte doit retrouver ce nombre sur /offres. En cache dix minutes :
    // c'est la page la plus vue du site, et le total ne bouge qu'une fois
    // par nuit.
    public function count(): JsonResponse
    {
        return response()->json(Cache::remember('offres.compteur', 600, function () {
            $jeuncy = JobOffer::query()->where('status', JobOfferStatus::PUBLISHED)->count();
            $partenaires = ExternalJobOffer::query()->where('status', ExternalJobOfferStatus::ACTIVE)->count();

            return ['jeuncy' => $jeuncy, 'partenaires' => $partenaires, 'total' => $jeuncy + $partenaires];
        }));
    }
}
