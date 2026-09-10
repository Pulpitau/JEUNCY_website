<?php

namespace App\Console\Commands;

use App\Enums\JobOfferStatus;
use App\Enums\NotificationType;
use App\Enums\PaymentStatus;
use App\Models\JobOffer;
use App\Models\Notification;
use App\Models\User;
use App\Services\JobOfferService;
use App\Services\MailService;
use App\Services\SubscriptionService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fin de la periode de mise en ligne achetee a l'offre.
 *
 * Depuis le 2026-09-10, un paiement a l'offre achete une periode (voir
 * config services.stripe.offer_publication_days) et non plus une publication
 * definitive. Cette commande porte les deux moments de cette periode :
 * le preavis, puis le retrait.
 *
 * POURQUOI LES DEUX DANS LA MEME COMMANDE. Chaque nouveau fichier a deployer
 * est une occasion de plus qu'il n'arrive pas sur le serveur — c'est arrive
 * cinq fois en septembre, toujours en silence (voir CLAUDE.md). Preavis et
 * retrait regardent la meme colonne, sur les memes offres, a un jour d'ecart :
 * les separer aurait double la surface de deploiement sans rien clarifier.
 *
 * ELLE NE TOUCHE JAMAIS : une offre d'essai gratuit (retiree par
 * ArchiveExpiredTrialOffers selon trial_started_at du compte, pas selon
 * l'offre), ni une offre publiee via un abonnement (publication illimitee),
 * ni l'offre d'un proprietaire actuellement abonne.
 */
class ExpireJobOffers extends Command
{
    protected $signature = 'job-offers:expire';

    protected $description = "Previent a l'approche de l'echeance, puis retire de la ligne les offres dont la periode de mise en ligne payee est ecoulee";

    // Delai du preavis. Trois jours : assez tot pour qu'un responsable qui ne
    // consulte pas la plateforme tous les jours ait le temps de decider, assez
    // tard pour que le message reste actionnable plutot qu'oublie.
    public const PREAVIS_JOURS = 3;

    public function __construct(
        private readonly JobOfferService $jobOfferService,
        private readonly SubscriptionService $subscriptionService,
        private readonly MailService $mailService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $prevenues = $this->prevenirAvantEcheance();
        $retirees = $this->retirerLesEchues();

        $this->info("{$prevenues} offre(s) arrivant a echeance signalee(s), {$retirees} offre(s) retiree(s) de la ligne.");

        return self::SUCCESS;
    }

    /**
     * Offres susceptibles d'etre concernees par une echeance.
     *
     * Le filtre sur payment_status est une ceinture doublee de bretelles :
     * ni l'essai ni l'abonnement ne posent d'expires_at (voir
     * JobOfferService), mais une offre passee d'un modele a l'autre pourrait
     * trainer une vieille date, et la retirer a tort couterait bien plus cher
     * que cette clause.
     */
    private function offresAEcheance(): Builder
    {
        return JobOffer::query()
            ->where('status', JobOfferStatus::PUBLISHED)
            ->whereNotNull('expires_at')
            ->whereNotIn('payment_status', [PaymentStatus::SUBSCRIPTION, PaymentStatus::TRIAL])
            ->with(['company.user', 'cfaOrganization.user']);
    }

    /**
     * Un abonne actif ne doit jamais voir une offre retiree : il paie
     * precisement pour la publication illimitee. Le cas se produit des qu'une
     * entreprise souscrit alors qu'elle a des offres payees a l'unite encore
     * en cours — le client qui paie le plus cher serait le seul a perdre son
     * annonce.
     */
    private function couvertParUnAbonnement(?User $proprietaire): bool
    {
        return $proprietaire !== null && $this->subscriptionService->hasActiveSubscription($proprietaire);
    }

    /**
     * Lien de la notification, qui porte AUSSI la deduplication : il contient
     * la date de fin, donc il change a chaque nouvelle periode payee. Un
     * preavis part une fois par periode, et la periode suivante en redeclenche
     * un — sans colonne supplementaire ni dependance a created_at, que le
     * modele Notification ne gere pas ($timestamps = false).
     */
    private function lienPreavis(JobOffer $offre): string
    {
        return '/mes-offres?offre='.$offre->id.'&fin='.$offre->expires_at->toDateString();
    }

    private function prevenirAvantEcheance(): int
    {
        $offres = $this->offresAEcheance()
            ->where('expires_at', '>', now())
            ->where('expires_at', '<=', now()->addDays(self::PREAVIS_JOURS))
            ->get();

        $parProprietaire = [];
        $proprietaires = [];
        $tarifs = [];
        $comptees = 0;

        foreach ($offres as $offre) {
            $proprietaire = $this->jobOfferService->ownerUser($offre);
            if (! $proprietaire || $this->couvertParUnAbonnement($proprietaire)) {
                continue;
            }

            $lien = $this->lienPreavis($offre);
            $dejaPrevenu = Notification::query()
                ->where('user_id', $proprietaire->id)
                ->where('type', NotificationType::JOB_OFFER_EXPIRING->value)
                ->where('link', $lien)
                ->exists();

            if ($dejaPrevenu) {
                continue;
            }

            $tarif = $this->jobOfferService->priceLabelFor($offre);
            $duree = $this->jobOfferService->publicationDurationLabel();
            $fin = $offre->expires_at->format('d/m/Y');

            $proprietaire->notifications()->create([
                'type' => NotificationType::JOB_OFFER_EXPIRING,
                'message' => "Ton offre \"{$offre->title}\" sort de la ligne le {$fin}. Remets-la en ligne pour {$duree} en payant {$tarif}.",
                'link' => $lien,
            ]);

            $proprietaires[$proprietaire->id] = $proprietaire;
            $parProprietaire[$proprietaire->id][] = ['titre' => $offre->title, 'fin' => $offre->expires_at];
            $tarifs[$proprietaire->id] = $tarif;
            $comptees++;
        }

        // Un email par proprietaire, pas un par offre : une entreprise dont
        // trois annonces tombent le meme jour recoit un message, pas trois.
        foreach ($parProprietaire as $id => $lignes) {
            $this->mailService->sendOffersExpiringEmail($proprietaires[$id]->email, $lignes, $tarifs[$id]);
        }

        return $comptees;
    }

    private function retirerLesEchues(): int
    {
        $offres = $this->offresAEcheance()->where('expires_at', '<=', now())->get();

        $parProprietaire = [];
        $proprietaires = [];
        $tarifs = [];
        $retirees = 0;

        foreach ($offres as $offre) {
            $proprietaire = $this->jobOfferService->ownerUser($offre);

            if ($this->couvertParUnAbonnement($proprietaire)) {
                continue;
            }

            // EXPIRED et non ARCHIVED : le statut dit la cause (la periode est
            // finie, pas un choix du proprietaire) et surtout il garde l'offre
            // payable, donc remettable en ligne (voir requirePayableOffer).
            // Les candidatures deja recues restent accessibles :
            // applications_unlocked_at n'est pas efface.
            $offre->update(['status' => JobOfferStatus::EXPIRED]);
            $retirees++;

            if (! $proprietaire) {
                continue;
            }

            $tarif = $this->jobOfferService->priceLabelFor($offre);
            $duree = $this->jobOfferService->publicationDurationLabel();

            $proprietaire->notifications()->create([
                'type' => NotificationType::JOB_OFFER_EXPIRING,
                'message' => "Ton offre \"{$offre->title}\" n'est plus en ligne. Remets-la en ligne pour {$duree} en payant {$tarif}.",
                'link' => '/mes-offres',
            ]);

            $proprietaires[$proprietaire->id] = $proprietaire;
            $parProprietaire[$proprietaire->id][] = $offre->title;
            $tarifs[$proprietaire->id] = $tarif;
        }

        foreach ($parProprietaire as $id => $titres) {
            $this->mailService->sendOffersExpiredEmail($proprietaires[$id]->email, $titres, $tarifs[$id]);
        }

        return $retirees;
    }
}
