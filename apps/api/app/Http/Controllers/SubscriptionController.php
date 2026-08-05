<?php

namespace App\Http\Controllers;

use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(private readonly SubscriptionService $service) {}

    public function checkout(Request $request): JsonResponse
    {
        $checkoutUrl = $this->service->createCheckoutSession($request->user());

        return response()->json(['checkout_url' => $checkoutUrl]);
    }

    public function mine(Request $request): JsonResponse
    {
        return response()->json($this->service->currentFor($request->user()));
    }

    public function cancel(Request $request): JsonResponse
    {
        return response()->json($this->service->cancel($request->user()));
    }
}
