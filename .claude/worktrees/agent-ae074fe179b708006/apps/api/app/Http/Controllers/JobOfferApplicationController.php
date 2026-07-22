<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\Http\Requests\Application\UpdateApplicationStatusRequest;
use App\Models\Application;
use App\Models\JobOffer;
use App\Services\ApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JobOfferApplicationController extends Controller
{
    public function __construct(private readonly ApplicationService $service) {}

    public function index(Request $request, JobOffer $jobOffer): JsonResponse
    {
        return response()->json($this->service->listForOffer($request->user(), $jobOffer));
    }

    public function updateStatus(UpdateApplicationStatusRequest $request, Application $application): JsonResponse
    {
        $status = ApplicationStatus::from($request->validated('status'));

        return response()->json($this->service->updateStatus($request->user(), $application, $status));
    }
}
