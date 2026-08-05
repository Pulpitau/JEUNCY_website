<?php

namespace App\Http\Controllers;

use App\Http\Requests\Company\StoreCompanyRequest;
use App\Http\Requests\Company\UpdateCompanyRequest;
use App\Http\Requests\Company\UploadCompanyLogoRequest;
use App\Services\CompanyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function __construct(private readonly CompanyService $service) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json($this->service->getForUser($request->user()));
    }

    public function store(StoreCompanyRequest $request): JsonResponse
    {
        $company = $this->service->createForUser($request->user(), $request->validated());

        return response()->json($company, 201);
    }

    public function update(UpdateCompanyRequest $request): JsonResponse
    {
        return response()->json($this->service->updateForUser($request->user(), $request->validated()));
    }

    public function uploadLogo(UploadCompanyLogoRequest $request): JsonResponse
    {
        return response()->json($this->service->uploadLogo($request->user(), $request->file('logo')));
    }

    public function removeLogo(Request $request): JsonResponse
    {
        return response()->json($this->service->removeLogo($request->user()));
    }
}
