<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListPaymentsRequest;
use App\Services\AdminService;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function __construct(private readonly AdminService $service) {}

    public function index(ListPaymentsRequest $request): JsonResponse
    {
        return response()->json($this->service->listPayments($request->validated()));
    }
}
