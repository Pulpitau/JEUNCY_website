<?php

namespace App\Http\Controllers;

use App\Services\CvService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeneratedCvController extends Controller
{
    public function __construct(private readonly CvService $cvService) {}

    public function store(Request $request): JsonResponse
    {
        return response()->json($this->cvService->generate($request->user()), 201);
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->cvService->listForUser($request->user()));
    }
}
