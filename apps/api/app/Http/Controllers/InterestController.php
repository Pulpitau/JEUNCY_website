<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\Interest\PassInterestsRequest;
use App\Http\Requests\Interest\StoreInterestRequest;
use App\Models\CandidateProfile;
use App\Models\JobOffer;
use App\Services\InterestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InterestController extends Controller
{
    public function __construct(private readonly InterestService $service) {}

    public function store(StoreInterestRequest $request): JsonResponse
    {
        $offer = JobOffer::findOrFail($request->validated('job_offer_id'));

        // candidate_profile_id n'est lu que pour un employeur : pour un
        // candidat, le service prend toujours SON profil, quel que soit le
        // corps de la requete.
        $cible = null;
        if ($request->user()->role !== UserRole::CANDIDATE) {
            $cibleId = $request->validated('candidate_profile_id');
            $cible = $cibleId === null ? null : CandidateProfile::find($cibleId);
        }

        return response()->json($this->service->like($request->user(), $offer, $cible), 201);
    }

    public function batch(PassInterestsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $offreIds = $request->user()->role === UserRole::CANDIDATE
            ? array_map('intval', $validated['job_offer_ids'] ?? [])
            : [(int) $validated['job_offer_id']];

        $passes = $this->service->passBatch(
            $request->user(),
            $offreIds,
            array_map('intval', $validated['candidate_profile_ids'] ?? []),
        );

        return response()->json(['passed' => $passes]);
    }

    public function destroyLast(Request $request): JsonResponse
    {
        return response()->json($this->service->undoLast($request->user()));
    }
}
