<?php

namespace App\Http\Controllers;

use App\Http\Requests\Cvtheque\SearchCvthequeRequest;
use App\Services\CvthequeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CvthequeController extends Controller
{
    public function __construct(private readonly CvthequeService $service) {}

    public function index(SearchCvthequeRequest $request): JsonResponse
    {
        return response()->json(
            $this->service->search($request->user(), $request->validated()),
        );
    }

    public function show(Request $request, int $candidateProfile): JsonResponse
    {
        return response()->json(
            $this->service->find($request->user(), $candidateProfile),
        );
    }

    // Permet au frontend de savoir s'il doit afficher la CVtheque ou l'ecran
    // d'accroche vers l'abonnement, sans avoir a provoquer une 402 volontaire.
    public function access(Request $request): JsonResponse
    {
        return response()->json([
            'has_access' => $this->service->hasAccess($request->user()),
        ]);
    }
}
