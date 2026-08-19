<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListPaymentsRequest;
use App\Models\Payment;
use App\Services\AdminService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function __construct(
        private readonly AdminService $service,
        private readonly PaymentService $paymentService,
    ) {}

    public function index(ListPaymentsRequest $request): JsonResponse
    {
        return response()->json($this->service->listPayments($request->validated()));
    }

    // Rembourse reellement chez Stripe : voir PaymentService::refund pour les
    // garde-fous (etat du paiement, reference Stripe exigee, base ecrite
    // seulement apres accord de Stripe).
    public function refund(Payment $payment): JsonResponse
    {
        return response()->json($this->paymentService->refund($payment));
    }
}
