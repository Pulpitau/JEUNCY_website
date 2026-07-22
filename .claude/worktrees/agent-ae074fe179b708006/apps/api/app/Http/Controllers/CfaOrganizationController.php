<?php

namespace App\Http\Controllers;

use App\Http\Requests\CfaOrganization\StoreCfaOrganizationRequest;
use App\Http\Requests\CfaOrganization\UpdateCfaOrganizationRequest;
use App\Services\CfaOrganizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CfaOrganizationController extends Controller
{
    public function __construct(private readonly CfaOrganizationService $service) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json($this->service->getForUser($request->user()));
    }

    public function store(StoreCfaOrganizationRequest $request): JsonResponse
    {
        $cfaOrganization = $this->service->createForUser($request->user(), $request->validated());

        return response()->json($cfaOrganization, 201);
    }

    public function update(UpdateCfaOrganizationRequest $request): JsonResponse
    {
        return response()->json($this->service->updateForUser($request->user(), $request->validated()));
    }
}
