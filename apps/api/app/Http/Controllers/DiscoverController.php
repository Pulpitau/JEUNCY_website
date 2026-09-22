<?php

namespace App\Http\Controllers;

use App\Http\Requests\Discover\DiscoverCandidatesRequest;
use App\Http\Requests\Discover\DiscoverOffersRequest;
use App\Models\JobOffer;
use App\Services\DiscoverService;
use Illuminate\Http\JsonResponse;

// Les deux piles de Decouvrir. Aucune logique ici : le service decide, le
// controleur ne fait que passer le plat.
class DiscoverController extends Controller
{
    public function __construct(private readonly DiscoverService $service) {}

    public function offers(DiscoverOffersRequest $request): JsonResponse
    {
        return response()->json($this->service->offersForCandidate(
            $request->user(),
            (int) ($request->validated('page') ?? 1),
        ));
    }

    public function candidates(DiscoverCandidatesRequest $request): JsonResponse
    {
        $offer = JobOffer::findOrFail($request->validated('job_offer_id'));

        return response()->json($this->service->candidatesForOffer(
            $request->user(),
            $offer,
            (int) ($request->validated('page') ?? 1),
        ));
    }
}
