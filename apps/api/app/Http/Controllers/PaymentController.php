<?php

namespace App\Http\Controllers;

use App\Models\JobOffer;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $service) {}

    public function checkout(Request $request, JobOffer $jobOffer): JsonResponse
    {
        $checkoutUrl = $this->service->createCheckoutSessionForOffer($request->user(), $jobOffer);

        return response()->json(['checkout_url' => $checkoutUrl]);
    }

    // Appele directement par Stripe (jamais par le frontend) : la signature du
    // corps brut de la requete fait office d'authentification, pas de JWT ici.
    public function webhook(Request $request): JsonResponse
    {
        $this->service->handleWebhook($request->getContent(), $request->header('Stripe-Signature', ''));

        return response()->json(['received' => true]);
    }
}
