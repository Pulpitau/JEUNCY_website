<?php

namespace App\Http\Controllers;

use App\Services\CfaOrganizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicCfaOrganizationController extends Controller
{
    public function __construct(private readonly CfaOrganizationService $service) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->service->searchPublic($request->query('city')));
    }

    public function show(int $cfaOrganization): JsonResponse
    {
        return response()->json($this->service->findPublic($cfaOrganization));
    }
}
