<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Http\Requests\Block\StoreBlockRequest;
use App\Models\CandidateProfile;
use App\Models\JobOffer;
use App\Models\UserBlock;
use App\Services\BlockService;
use App\Services\JobOfferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlockController extends Controller
{
    public function __construct(
        private readonly BlockService $service,
        private readonly JobOfferService $jobOfferService,
    ) {}

    public function store(StoreBlockRequest $request): JsonResponse
    {
        $cible = $this->resoudreCible($request->validated());

        return response()->json($this->service->block($request->user(), $cible), 201);
    }

    public function destroy(Request $request, UserBlock $userBlock): JsonResponse
    {
        $this->service->unblock($request->user(), $userBlock);

        return response()->json(['deleted' => true]);
    }

    /**
     * Traduit une carte ou une offre en compte. Le client ne connait jamais
     * l'identifiant du compte d'en face (voir StoreBlockRequest).
     *
     * @param  array<string, mixed>  $data
     */
    private function resoudreCible(array $data): int
    {
        if (! empty($data['user_id'])) {
            return (int) $data['user_id'];
        }

        if (! empty($data['candidate_profile_id'])) {
            $profile = CandidateProfile::find((int) $data['candidate_profile_id']);
            $cible = $profile?->user_id;
        } else {
            $offre = JobOffer::find((int) ($data['job_offer_id'] ?? 0));
            $cible = $offre === null ? null : $this->jobOfferService->ownerUser($offre)?->id;
        }

        if ($cible === null) {
            throw new ApiException('BLOCK_TARGET_NOT_FOUND', "Impossible d'identifier ce compte.", 404);
        }

        return $cible;
    }
}
