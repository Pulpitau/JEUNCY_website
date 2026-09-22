<?php

namespace App\Http\Controllers;

use App\Enums\ExternalInterestDecision;
use App\Http\Requests\ExternalInterest\StoreExternalInterestRequest;
use App\Models\ExternalInterest;
use App\Models\ExternalJobOffer;
use App\Services\ExternalInterestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExternalInterestController extends Controller
{
    public function __construct(private readonly ExternalInterestService $service) {}

    public function store(StoreExternalInterestRequest $request): JsonResponse
    {
        $offer = ExternalJobOffer::findOrFail($request->validated('external_job_offer_id'));
        $decision = ExternalInterestDecision::from($request->validated('decision'));

        return response()->json($this->service->decide($request->user(), $offer, $decision), 201);
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->service->listKept($request->user()));
    }

    public function done(Request $request, ExternalInterest $externalInterest): JsonResponse
    {
        return response()->json($this->service->markDone($request->user(), $externalInterest));
    }

    public function destroyLast(Request $request): JsonResponse
    {
        return response()->json($this->service->undoLast($request->user()));
    }
}
