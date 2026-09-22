<?php

namespace App\Http\Controllers;

use App\Http\Requests\Report\StoreReportRequest;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $service) {}

    public function store(StoreReportRequest $request): JsonResponse
    {
        return response()->json($this->service->report($request->user(), $request->validated()), 201);
    }
}
