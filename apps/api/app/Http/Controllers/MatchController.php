<?php

namespace App\Http\Controllers;

use App\Models\OfferInterest;
use App\Services\MatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MatchController extends Controller
{
    public function __construct(private readonly MatchService $service) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->service->listForUser($request->user()));
    }

    public function show(Request $request, OfferInterest $offerInterest): JsonResponse
    {
        return response()->json($this->service->findForUser($request->user(), $offerInterest));
    }
}
