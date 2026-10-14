<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\Http\Requests\Application\UpdateApplicationStatusRequest;
use App\Models\Application;
use App\Models\JobOffer;
use App\Services\ApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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

    // Le PDF lui-meme, jamais son adresse : meme montage que
    // CvthequeController::downloadCv, hors enveloppe { success, data }.
    public function downloadCv(Request $request, Application $application): Response
    {
        $cv = $this->service->cvForEmployer($request->user(), $application);

        return response($cv['contents'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$cv['filename'].'"',
        ]);
    }
}
