<?php

namespace App\Http\Controllers;

use App\Http\Requests\Contact\SendContactMessageRequest;
use App\Services\MailService;
use Illuminate\Http\JsonResponse;

class ContactController extends Controller
{
    public function __construct(private readonly MailService $mailService) {}

    // Coordonnees publiques affichees sur la page Contact. Servies par l'API
    // plutot que codees dans le frontend : changer un numero de telephone ne
    // doit pas obliger a reconstruire et redeployer le bundle JavaScript.
    public function details(): JsonResponse
    {
        return response()->json([
            'email' => config('services.contact.email'),
            'phone' => config('services.contact.phone') ?: null,
        ]);
    }

    public function send(SendContactMessageRequest $request): JsonResponse
    {
        $data = $request->validated();

        $this->mailService->sendContactMessage(
            fromName: $data['name'],
            fromEmail: $data['email'],
            subject: $data['subject'],
            message: $data['message'],
            organization: $data['organization'] ?? null,
        );

        // Toujours un succes cote visiteur, meme si Resend echoue : MailService
        // logge l'incident sans le faire remonter (voir son commentaire), et un
        // prospect n'a pas a subir un message d'erreur pour une panne interne.
        return response()->json(['sent' => true]);
    }
}
