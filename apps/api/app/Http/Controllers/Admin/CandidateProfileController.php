<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListCandidateProfilesRequest;
use App\Http\Requests\Admin\UpdateCandidateNameRequest;
use App\Models\CandidateProfile;
use App\Services\AdminService;
use Illuminate\Http\JsonResponse;

class CandidateProfileController extends Controller
{
    public function __construct(private readonly AdminService $service) {}

    public function index(ListCandidateProfilesRequest $request): JsonResponse
    {
        return response()->json($this->service->listCandidateProfiles($request->validated()));
    }

    public function updateName(
        UpdateCandidateNameRequest $request,
        CandidateProfile $candidateProfile,
    ): JsonResponse {
        return response()->json($this->service->updateCandidateName(
            $candidateProfile,
            $request->validated('first_name'),
            $request->validated('last_name'),
        ));
    }
}
