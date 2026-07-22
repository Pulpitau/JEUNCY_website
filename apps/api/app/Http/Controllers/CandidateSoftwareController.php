<?php

namespace App\Http\Controllers;

use App\Http\Requests\CandidateProfile\SyncSoftwareRequest;
use App\Services\CandidateProfileService;
use Illuminate\Http\JsonResponse;

class CandidateSoftwareController extends Controller
{
    public function __construct(private readonly CandidateProfileService $service) {}

    public function sync(SyncSoftwareRequest $request): JsonResponse
    {
        return response()->json($this->service->syncSoftware($request->user(), $request->validated('names')));
    }
}
