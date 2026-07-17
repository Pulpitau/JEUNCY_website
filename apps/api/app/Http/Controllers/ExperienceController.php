<?php

namespace App\Http\Controllers;

use App\Http\Requests\CandidateProfile\StoreExperienceRequest;
use App\Http\Requests\CandidateProfile\UpdateExperienceRequest;
use App\Models\Experience;
use App\Services\CandidateProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExperienceController extends Controller
{
    public function __construct(private readonly CandidateProfileService $service) {}

    public function store(StoreExperienceRequest $request): JsonResponse
    {
        $experience = $this->service->addExperience($request->user(), $request->validated());

        return response()->json($experience, 201);
    }

    public function update(UpdateExperienceRequest $request, Experience $experience): JsonResponse
    {
        return response()->json(
            $this->service->updateExperience($request->user(), $experience, $request->validated()),
        );
    }

    public function destroy(Request $request, Experience $experience): JsonResponse
    {
        $this->service->deleteExperience($request->user(), $experience);

        return response()->json(['deleted' => true]);
    }
}
