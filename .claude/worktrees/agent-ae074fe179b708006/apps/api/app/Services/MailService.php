<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MailService
{
    public function sendPasswordResetEmail(string $to, string $resetUrl): void
    {
        $apiKey = config('services.resend.key');

        // Sans cle configuree (dev sans compte Resend), on log au lieu d'envoyer.
        if (! $apiKey) {
            Log::warning("RESEND_API_KEY absent : email de reinitialisation non envoye a {$to} (lien: {$resetUrl})");

            return;
        }

        Http::withToken($apiKey)
            ->post('https://api.resend.com/emails', [
                'from' => config('services.resend.from'),
                'to' => $to,
                'subject' => 'Réinitialise ton mot de passe Jeuncy',
                'html' => <<<HTML
                    <p>Tu as demandé la réinitialisation de ton mot de passe Jeuncy.</p>
                    <p><a href="{$resetUrl}">Clique ici pour choisir un nouveau mot de passe</a></p>
                    <p>Ce lien expire dans 1 heure. Si tu n'es pas à l'origine de cette demande, ignore cet email.</p>
                    HTML,
            ])
            ->throw();
    }
}
