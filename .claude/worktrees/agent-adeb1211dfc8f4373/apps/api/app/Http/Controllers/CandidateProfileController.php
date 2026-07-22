<?php

namespace App\Http\Controllers;

use App\Http\Requests\CandidateProfile\StoreCandidateProfileRequest;
use App\Http\Requests\CandidateProfile\UpdateCandidateProfileRequest;
use App\Services\CandidateProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CandidateProfileController extends Controller
{
    public function __construct(private readonly CandidateProfileService $service) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json($this->service->getForUser($request->user()));
    }

    public function store(StoreCandidateProfileRequest $request): JsonResponse
    {
        $profile = $this->service->createForUser($request->user(), $request->validated());

        return response()->json($profile, 201);
    }

    public function update(UpdateCandidateProfileRequest $request): JsonResponse
    {
        return response()->json($this->service->updateForUser($request->user(), $request->validated()));
    }
}
