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

    // Declenche par JobOfferService::publishViaTrialForUser au tout premier
    // appel (demarrage de l'essai), pas a chaque offre publiee via l'essai.
    // $priceLabel : tarif de republication apres expiration de l'essai,
    // different entreprise/CFA (voir JobOfferService::priceLabelFor).
    public function sendTrialStartedEmail(string $to, string $organizationName, string $priceLabel): void
    {
        $apiKey = config('services.resend.key');

        if (! $apiKey) {
            Log::warning("RESEND_API_KEY absent : email de debut d'essai gratuit non envoye a {$to}");

            return;
        }

        $frontendUrl = rtrim(config('app.frontend_url'), '/');
        $safeName = e($organizationName);

        Http::withToken($apiKey)
            ->post('https://api.resend.com/emails', [
                'from' => config('services.resend.from'),
                'to' => $to,
                'subject' => 'Ta période d\'essai gratuite Jeuncy a démarré',
                'html' => <<<HTML
                    <p>Bonjour,</p>
                    <p>La période d'essai gratuite de {$safeName} vient de démarrer sur Jeuncy.</p>
                    <p>Pendant 15 jours, publie gratuitement 1 offre. Passé ce délai, l'offre publiée via l'essai sera archivée et il faudra payer {$priceLabel} pour la republier.</p>
                    <p><a href="{$frontendUrl}/mes-offres">Gérer mes offres</a></p>
                    HTML,
            ])
            ->throw();
    }

    // Declenche par ArchiveExpiredTrialOffers, une fois par entreprise/CFA
    // touche. $priceLabel : tarif de republication, different entreprise/CFA
    // (voir JobOfferService::priceLabelFor) — toutes les offres archivees en
    // meme temps appartiennent au meme proprietaire, donc au meme tarif.
    public function sendTrialEndedEmail(string $to, array $archivedOfferTitles, string $priceLabel): void
    {
        $apiKey = config('services.resend.key');

        if (! $apiKey) {
            Log::warning("RESEND_API_KEY absent : email de fin d'essai gratuit non envoye a {$to}");

            return;
        }

        $frontendUrl = rtrim(config('app.frontend_url'), '/');
        $list = collect($archivedOfferTitles)
            ->map(fn (string $title) => '<li>'.e($title).'</li>')
            ->implode('');

        Http::withToken($apiKey)
            ->post('https://api.resend.com/emails', [
                'from' => config('services.resend.from'),
                'to' => $to,
                'subject' => 'Ta période d\'essai gratuite Jeuncy est terminée',
                'html' => <<<HTML
                    <p>Bonjour,</p>
                    <p>Ta période d'essai gratuite de 15 jours sur Jeuncy est terminée. Les offres suivantes ont été archivées et ne sont plus visibles publiquement :</p>
                    <ul>{$list}</ul>
                    <p>Rends-toi sur ton espace "Mes offres" pour payer {$priceLabel} et republier chacune d'elles.</p>
                    <p><a href="{$frontendUrl}/mes-offres">Gérer mes offres</a></p>
                    HTML,
            ])
            ->throw();
    }
}
