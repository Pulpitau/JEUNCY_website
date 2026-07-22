<?php

namespace App\Http\Controllers;

use App\Http\Requests\Application\StoreApplicationRequest;
use App\Models\JobOffer;
use App\Services\ApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApplicationController extends Controller
{
    public function __construct(private readonly ApplicationService $service) {}

    public function store(StoreApplicationRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $jobOffer = JobOffer::findOrFail($validated['job_offer_id']);
        $application = $this->service->applyForUser(
            $request->user(),
            $jobOffer,
            $validated['cover_letter'] ?? null,
        );

        return response()->json($application, 201);
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->service->listForCandidate($request->user()));
    }
}
