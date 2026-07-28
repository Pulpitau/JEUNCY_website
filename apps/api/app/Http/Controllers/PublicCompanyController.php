<?php

namespace App\Http\Controllers;

use App\Services\CompanyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicCompanyController extends Controller
{
    public function __construct(private readonly CompanyService $service) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->service->searchPublic($request->query('city')));
    }

    public function show(int $company): JsonResponse
    {
        return response()->json($this->service->findPublic($company));
    }
}
