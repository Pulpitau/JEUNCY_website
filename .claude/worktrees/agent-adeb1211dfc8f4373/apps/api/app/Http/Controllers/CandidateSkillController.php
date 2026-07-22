<?php

namespace App\Http\Controllers;

use App\Http\Requests\CandidateProfile\SyncSkillsRequest;
use App\Services\CandidateProfileService;
use Illuminate\Http\JsonResponse;

class CandidateSkillController extends Controller
{
    public function __construct(private readonly CandidateProfileService $service) {}

    public function sync(SyncSkillsRequest $request): JsonResponse
    {
        return response()->json($this->service->syncSkills($request->user(), $request->validated('names')));
    }
}
