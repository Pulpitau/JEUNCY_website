<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminService;
use Illuminate\Http\JsonResponse;

class StatsController extends Controller
{
    public function __construct(private readonly AdminService $service) {}

    public function index(): JsonResponse
    {
        return response()->json($this->service->stats());
    }
}
