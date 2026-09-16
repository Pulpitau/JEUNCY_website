<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExternalEmployerBlock;
use App\Models\ExternalJobOffer;
use App\Services\ExternalJobOfferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// Audit du filtre des ecoles sur les offres importees, et blocage manuel
// d'un employeur (voir ExternalJobOfferService).
class ExternalJobOfferController extends Controller
{
    public function __construct(private readonly ExternalJobOfferService $service) {}

    public function stats(): JsonResponse
    {
        return response()->json($this->service->adminStats());
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['sometimes', 'in:ACTIVE,EXCLUDED'],
            'q' => ['sometimes', 'string', 'max:255'],
        ]);

        return response()->json($this->service->adminList($filters));
    }

    public function blockEmployer(Request $request, ExternalJobOffer $externalJobOffer): JsonResponse
    {
        $data = $request->validate(['reason' => ['sometimes', 'nullable', 'string', 'max:255']]);

        return response()->json($this->service->blockEmployerFromOffer($request->user(), $externalJobOffer, $data['reason'] ?? null), 201);
    }

    public function blocks(): JsonResponse
    {
        return response()->json($this->service->listBlocks());
    }

    public function removeBlock(ExternalEmployerBlock $externalEmployerBlock): JsonResponse
    {
        $this->service->removeBlock($externalEmployerBlock);

        return response()->json(['deleted' => true]);
    }
}
