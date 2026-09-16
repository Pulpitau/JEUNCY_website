<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExternalJobOffer\SearchExternalJobOffersRequest;
use App\Services\ExternalJobOfferService;
use Illuminate\Http\JsonResponse;

// Offres importees (La bonne alternance), publiques, sans authentification —
// comme PublicJobOfferController pour les offres Jeuncy.
class PublicExternalJobOfferController extends Controller
{
    public function __construct(private readonly ExternalJobOfferService $service) {}

    public function index(SearchExternalJobOffersRequest $request): JsonResponse
    {
        return response()->json($this->service->searchPublic($request->validated()));
    }

    public function show(int $externalJobOffer): JsonResponse
    {
        return response()->json($this->service->findPublic($externalJobOffer));
    }
}
