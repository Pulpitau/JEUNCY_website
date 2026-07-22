<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListJobOffersRequest;
use App\Models\JobOffer;
use App\Services\AdminService;
use Illuminate\Http\JsonResponse;

class JobOfferController extends Controller
{
    public function __construct(private readonly AdminService $service) {}

    public function index(ListJobOffersRequest $request): JsonResponse
    {
        return response()->json($this->service->listJobOffers($request->validated()));
    }

    public function archive(JobOffer $jobOffer): JsonResponse
    {
        return response()->json($this->service->forceArchiveJobOffer($jobOffer));
    }
}
