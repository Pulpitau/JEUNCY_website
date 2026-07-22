<?php

namespace App\Http\Controllers;

use App\Http\Requests\JobOffer\StoreJobOfferRequest;
use App\Http\Requests\JobOffer\UpdateJobOfferRequest;
use App\Models\JobOffer;
use App\Services\JobOfferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JobOfferController extends Controller
{
    public function __construct(private readonly JobOfferService $service) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->service->listOwn($request->user()));
    }

    public function store(StoreJobOfferRequest $request): JsonResponse
    {
        $jobOffer = $this->service->createForUser($request->user(), $request->validated());

        return response()->json($jobOffer, 201);
    }

    public function update(UpdateJobOfferRequest $request, JobOffer $jobOffer): JsonResponse
    {
        return response()->json($this->service->updateForUser($request->user(), $jobOffer, $request->validated()));
    }

    public function archive(Request $request, JobOffer $jobOffer): JsonResponse
    {
        return response()->json($this->service->archiveForUser($request->user(), $jobOffer));
    }
}
