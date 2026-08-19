<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
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

        $body = <<<HTML
            <p>Tu as demandé la réinitialisation de ton mot de passe Jeuncy.</p>
            {$this->ctaButton('Choisir un nouveau mot de passe', $resetUrl)}
            <p style="color:#6b7280;font-size:13px;">Ce lien expire dans 1 heure. Si tu n'es pas à l'origine de cette demande, ignore cet email.</p>
            HTML;

        $this->send($apiKey, $to, 'Réinitialise ton mot de passe Jeuncy', $this->wrapEmailHtml('Réinitialise ton mot de passe', $body));
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

        $body = <<<HTML
            <p>Bonjour,</p>
            <p>La période d'essai gratuite de {$safeName} vient de démarrer sur Jeuncy.</p>
            <p>Pendant 15 jours, publie gratuitement 1 offre. Passé ce délai, l'offre publiée via l'essai sera archivée et il faudra payer {$priceLabel} pour la republier.</p>
            {$this->ctaButton('Gérer mes offres', $frontendUrl.'/mes-offres')}
            HTML;

        $this->send($apiKey, $to, 'Ta période d\'essai gratuite Jeuncy a démarré', $this->wrapEmailHtml('Ton essai gratuit a démarré', $body));
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

        $body = <<<HTML
            <p>Bonjour,</p>
            <p>Ta période d'essai gratuite de 15 jours sur Jeuncy est terminée. Les offres suivantes ont été archivées et ne sont plus visibles publiquement :</p>
            <ul style="padding-left:20px;color:#374151;">{$list}</ul>
            <p>Rends-toi sur ton espace "Mes offres" pour payer {$priceLabel} et republier chacune d'elles.</p>
            {$this->ctaButton('Gérer mes offres', $frontendUrl.'/mes-offres')}
            HTML;

        $this->send($apiKey, $to, 'Ta période d\'essai gratuite Jeuncy est terminée', $this->wrapEmailHtml('Ton essai gratuit est terminé', $body));
    }

    // Declenche par SendVideoRoomReminders (cron horaire) pour l'hote et,
    // s'il existe, le participant d'une salle programmee dans l'heure qui
    // suit. $joinUrl pointe vers la page Jeuncy /demo/{jitsi_room_name}
    // (jamais directement vers meet.jit.si, voir CLAUDE.md section 7).
    public function sendVideoRoomReminderEmail(string $to, \DateTimeInterface $scheduledAt, string $joinUrl): void
    {
        $apiKey = config('services.resend.key');

        if (! $apiKey) {
            Log::warning("RESEND_API_KEY absent : rappel de visio non envoye a {$to}");

            return;
        }

        $formattedTime = $scheduledAt->format('d/m/Y à H:i');

        $body = <<<HTML
            <p>Bonjour,</p>
            <p>Petit rappel : une visioconférence Jeuncy à laquelle tu participes est prévue le <strong>{$formattedTime}</strong>.</p>
            {$this->ctaButton('Rejoindre la visio', $joinUrl)}
            HTML;

        $this->send($apiKey, $to, 'Rappel : ta visioconférence Jeuncy approche', $this->wrapEmailHtml('Ta visio approche', $body));
    }

    // Prevenu a la reception d'une candidature.
    //
    // La notification in-app existait deja, mais elle suppose que le recruteur
    // se connecte pour la voir. Un candidat qui attend trois jours une reponse
    // qui ne vient pas parce que personne n'a ouvert le tableau de bord, c'est
    // une candidature perdue des deux cotes.
    //
    // Aucune donnee du candidat au-dela de son nom : le detail de la
    // candidature se consulte dans l'application, derriere authentification.
    // Une boite mail se transfere, s'archive et fuit — l'email sert a faire
    // revenir, pas a transporter le dossier.
    public function sendNewApplicationEmail(
        string $to,
        string $candidateName,
        string $offerTitle,
        string $applicationsUrl,
    ): void {
        $apiKey = config('services.resend.key');

        if (! $apiKey) {
            Log::warning("RESEND_API_KEY absent : notification de candidature non envoyee a {$to}");

            return;
        }

        $safeCandidate = e($candidateName);
        $safeOffer = e($offerTitle);

        $body = <<<HTML
            <p>Bonjour,</p>
            <p><strong>{$safeCandidate}</strong> vient de postuler à votre offre « {$safeOffer} ».</p>
            <p>Retrouvez son profil et son CV dans votre espace Jeuncy.</p>
            {$this->ctaButton('Voir la candidature', $applicationsUrl)}
            HTML;

        $this->send(
            $apiKey,
            $to,
            "Nouvelle candidature : {$offerTitle}",
            $this->wrapEmailHtml('Une nouvelle candidature', $body),
        );
    }

    // Prevenu quand sa candidature change de statut.
    //
    // Meme raison en miroir : c'est le moment ou le candidat a le plus besoin
    // d'etre informe, et celui ou il est le moins susceptible d'etre connecte.
    public function sendApplicationStatusChangedEmail(
        string $to,
        string $offerTitle,
        string $statusLabel,
        string $applicationsUrl,
    ): void {
        $apiKey = config('services.resend.key');

        if (! $apiKey) {
            Log::warning("RESEND_API_KEY absent : changement de statut non envoye a {$to}");

            return;
        }

        $safeOffer = e($offerTitle);
        $safeStatus = e($statusLabel);

        $body = <<<HTML
            <p>Bonjour,</p>
            <p>Ta candidature à l'offre « {$safeOffer} » vient de passer au statut <strong>{$safeStatus}</strong>.</p>
            {$this->ctaButton('Suivre ma candidature', $applicationsUrl)}
            HTML;

        $this->send(
            $apiKey,
            $to,
            "Ta candidature a évolué : {$offerTitle}",
            $this->wrapEmailHtml('Ta candidature a évolué', $body),
        );
    }

    // Message du formulaire de contact public, transmis a l'equipe Jeuncy.
    //
    // reply_to porte l'adresse du VISITEUR : repondre depuis sa boite mail doit
    // ecrire au prospect, pas a soi-meme. Sans ca, chaque reponse partirait
    // vers no-reply@jeuncy.com et se perdrait.
    //
    // L'expediteur reste l'adresse Resend verifiee du domaine : usurper
    // l'adresse du visiteur ferait echouer SPF/DKIM et enverrait le message
    // droit dans les indesirables.
    public function sendContactMessage(
        string $fromName,
        string $fromEmail,
        string $subject,
        string $message,
        ?string $organization = null,
    ): void {
        $apiKey = config('services.resend.key');
        $to = config('services.contact.email');

        if (! $apiKey) {
            Log::warning("RESEND_API_KEY absent : message de contact de {$fromEmail} non transmis a {$to}");

            return;
        }

        $safeName = e($fromName);
        $safeEmail = e($fromEmail);
        $safeSubject = e($subject);
        // nl2br sur le message echappe : conserve les sauts de ligne du
        // visiteur sans laisser passer de HTML.
        $safeMessage = nl2br(e($message));
        $orgLine = $organization
            ? '<p style="margin:0;"><strong>Structure :</strong> '.e($organization).'</p>'
            : '';

        $body = <<<HTML
            <p>Nouveau message reçu depuis le formulaire de contact du site.</p>
            <div style="border-left:3px solid #FF2D55;padding-left:14px;margin:20px 0;">
                <p style="margin:0;"><strong>De :</strong> {$safeName} &lt;{$safeEmail}&gt;</p>
                {$orgLine}
                <p style="margin:0;"><strong>Objet :</strong> {$safeSubject}</p>
            </div>
            <p style="white-space:pre-line;">{$safeMessage}</p>
            <p style="font-size:13px;color:#6b7280;">Réponds directement à cet email pour écrire à {$safeEmail}.</p>
            HTML;

        $this->send(
            $apiKey,
            $to,
            "[Contact Jeuncy] {$subject}",
            $this->wrapEmailHtml('Nouveau message de contact', $body),
            replyTo: $fromEmail,
        );
    }

    // Envoi centralise : un echec Resend (recipient invalide, quota, panne
    // ponctuelle...) ne doit jamais faire echouer l'action metier qui a
    // declenche l'email (publication d'offre, reinitialisation de mot de
    // passe...) - decouvert le 2026-08-05 en testant l'essai gratuit avec un
    // destinataire invalide : l'offre etait bien publiee en base mais la
    // requete HTTP entiere remontait en 500 a cause du ->throw() ici, rendant
    // une action reussie visible comme une erreur cote entreprise. On logge
    // l'echec au lieu de le laisser remonter.
    private function send(
        string $apiKey,
        string $to,
        string $subject,
        string $html,
        ?string $replyTo = null,
    ): void {
        try {
            $payload = [
                'from' => config('services.resend.from'),
                'to' => $to,
                'subject' => $subject,
                'html' => $html,
            ];

            if ($replyTo) {
                $payload['reply_to'] = $replyTo;
            }

            Http::withToken($apiKey)
                ->post('https://api.resend.com/emails', $payload)
                ->throw();
        } catch (RequestException|ConnectionException $e) {
            Log::error("Echec d'envoi Resend a {$to} (\"{$subject}\") : {$e->getMessage()}");
        }
    }

    // Bouton CTA style inline (les clients mail ignorent trop souvent les
    // classes/feuilles de style externes, tout est en attributs style="").
    private function ctaButton(string $label, string $url): string
    {
        $safeUrl = e($url);

        return <<<HTML
            <p style="margin:24px 0;">
                <a href="{$safeUrl}" style="background:#FF2D55;color:#ffffff;text-decoration:none;font-family:Arial,sans-serif;font-weight:bold;font-size:14px;padding:12px 24px;border-radius:6px;display:inline-block;">{$label}</a>
            </p>
            HTML;
    }

    // Habillage commun aux emails transactionnels : bandeau degrade en tete,
    // titre, contenu, puis logo Jeuncy en pied de page. Le logo est charge
    // depuis une vraie URL (route web.php /branding/logo.png) et non depuis
    // un data: URI base64 : Gmail (et d'autres clients mail) ignore/bloque
    // les images data: URI inline dans le HTML d'un email, contrairement au
    // PDF/dompdf ou ce procede fonctionne (constate via un envoi reel, image
    // absente a la reception malgre un HTML techniquement valide).
    private function wrapEmailHtml(string $heading, string $bodyHtml): string
    {
        $logoUrl = e(rtrim(config('app.url'), '/').'/branding/logo.png');
        $logoHtml = "<img src=\"{$logoUrl}\" alt=\"Jeuncy\" width=\"40\" height=\"40\" style=\"display:block;margin:0 auto 8px;border-radius:50%;\" />";

        return <<<HTML
            <div style="background:#FAFAF8;padding:32px 16px;font-family:Arial,sans-serif;">
                <div style="max-width:480px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #eeeeee;">
                    <div style="height:6px;background:linear-gradient(90deg,#FF2D55,#FF8A32);"></div>
                    <div style="padding:32px;">
                        <h1 style="color:#061D4F;font-size:20px;margin:0 0 16px;">{$heading}</h1>
                        <div style="color:#374151;font-size:14px;line-height:1.6;">{$bodyHtml}</div>
                    </div>
                    <div style="padding:20px 32px;border-top:1px solid #eeeeee;text-align:center;">
                        {$logoHtml}
                        <p style="color:#9ca3af;font-size:12px;margin:0;">Jeuncy — Ton alternance commence ici.</p>
                    </div>
                </div>
            </div>
            HTML;
    }
}
