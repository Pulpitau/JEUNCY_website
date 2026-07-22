<?php

namespace App\Http\Controllers;

use App\Http\Requests\CandidateProfile\UploadProfilePhotoRequest;
use App\Services\CandidateProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfilePhotoController extends Controller
{
    public function __construct(private readonly CandidateProfileService $service) {}

    public function store(UploadProfilePhotoRequest $request): JsonResponse
    {
        $profile = $this->service->updatePhoto($request->user(), $request->file('photo'));

        return response()->json($profile);
    }

    public function destroy(Request $request): JsonResponse
    {
        return response()->json($this->service->removePhoto($request->user()));
    }
}
