<?php

namespace App\Http\Controllers;

use App\Http\Requests\JobOffer\SearchJobOffersRequest;
use App\Services\JobOfferService;
use Illuminate\Http\JsonResponse;

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
}
