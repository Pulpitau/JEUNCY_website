<?php

namespace App\Http\Controllers;

use App\Http\Requests\CandidateProfile\StoreCandidateProfileRequest;
use App\Http\Requests\CandidateProfile\UpdateCandidateLocationRequest;
use App\Http\Requests\CandidateProfile\UpdateCandidatePreferencesRequest;
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

    public function updatePreferences(UpdateCandidatePreferencesRequest $request): JsonResponse
    {
        return response()->json($this->service->updatePreferences($request->user(), $request->validated()));
    }

    // La reponse ne renvoie JAMAIS les coordonnees, meme arrondies : le
    // client vient de les envoyer, les lui relire n'apprend rien et ferait de
    // cette route un endroit ou une position circule sans raison.
    public function updateLocation(UpdateCandidateLocationRequest $request): JsonResponse
    {
        $profile = $this->service->setDeviceLocation(
            $request->user(),
            (float) $request->validated('latitude'),
            (float) $request->validated('longitude'),
        );

        return response()->json([
            'location_source' => 'DEVICE',
            'device_located_at' => $profile->device_located_at,
        ]);
    }

    public function clearLocation(Request $request): JsonResponse
    {
        $profile = $this->service->clearDeviceLocation($request->user());

        // Sans GPS, la pile retombe sur la position geocodee du profil ; sans
        // elle non plus, sur le departement puis la France (DiscoverService).
        return response()->json([
            'location_source' => $profile->hasProfileCoordinates() ? 'PROFILE' : null,
        ]);
    }
}
