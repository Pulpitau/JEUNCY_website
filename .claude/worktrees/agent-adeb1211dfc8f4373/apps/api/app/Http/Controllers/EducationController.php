<?php

namespace App\Http\Controllers;

use App\Http\Requests\CandidateProfile\StoreEducationRequest;
use App\Http\Requests\CandidateProfile\UpdateEducationRequest;
use App\Models\Education;
use App\Services\CandidateProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EducationController extends Controller
{
    public function __construct(private readonly CandidateProfileService $service) {}

    public function store(StoreEducationRequest $request): JsonResponse
    {
        $education = $this->service->addEducation($request->user(), $request->validated());

        return response()->json($education, 201);
    }

    public function update(UpdateEducationRequest $request, Education $education): JsonResponse
    {
        return response()->json(
            $this->service->updateEducation($request->user(), $education, $request->validated()),
        );
    }

    public function destroy(Request $request, Education $education): JsonResponse
    {
        $this->service->deleteEducation($request->user(), $education);

        return response()->json(['deleted' => true]);
    }
}
