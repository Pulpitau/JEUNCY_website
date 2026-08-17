<?php

namespace App\Http\Controllers;

use App\Http\Requests\CfaOrganization\SearchCfaOrganizationsRequest;
use App\Services\CfaOrganizationService;
use Illuminate\Http\JsonResponse;

class PublicCfaOrganizationController extends Controller
{
    public function __construct(private readonly CfaOrganizationService $service) {}

    public function index(SearchCfaOrganizationsRequest $request): JsonResponse
    {
        return response()->json($this->service->searchPublic($request->validated()));
    }

    public function show(int $cfaOrganization): JsonResponse
    {
        return response()->json($this->service->findPublic($cfaOrganization));
    }
}
