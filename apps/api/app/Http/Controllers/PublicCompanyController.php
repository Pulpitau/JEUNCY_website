<?php

namespace App\Http\Controllers;

use App\Http\Requests\Company\SearchCompaniesRequest;
use App\Services\CompanyService;
use Illuminate\Http\JsonResponse;

class PublicCompanyController extends Controller
{
    public function __construct(private readonly CompanyService $service) {}

    public function index(SearchCompaniesRequest $request): JsonResponse
    {
        return response()->json($this->service->searchPublic($request->validated()));
    }

    public function show(int $company): JsonResponse
    {
        return response()->json($this->service->findPublic($company));
    }
}
