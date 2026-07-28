<?php

namespace App\Console\Commands;

use App\Enums\JobOfferStatus;
use App\Enums\NotificationType;
use App\Enums\PaymentStatus;
use App\Models\JobOffer;
use App\Models\User;
use App\Services\JobOfferService;
use App\Services\MailService;
use Illuminate\Console\Command;

class ArchiveExpiredTrialOffers extends Command
{
    protected $signature = 'job-offers:archive-expired-trials';

    protected $description = "Archive les offres publiees via la periode d'essai gratuite dont l'essai (15 jours) est termine, pour l'entreprise/le CFA qui les a publiees";

    public function __construct(
        private readonly JobOfferService $jobOfferService,
        private readonly MailService $mailService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        // Une offre publiee via l'essai est reperee par payment_status TRIAL
        // (jamais touche par ExpireJobOffers, qui ne regarde que expires_at) :
        // l'entreprise/le CFA proprietaire determine si SON essai (15 jours
        // depuis trial_started_at) est termine, pas la date de l'offre.
        $cutoff = now()->subDays(JobOfferService::TRIAL_DURATION_DAYS);

        $offers = JobOffer::query()
            ->where('status', JobOfferStatus::PUBLISHED)
            ->where('payment_status', PaymentStatus::TRIAL)
            ->where(function ($query) use ($cutoff) {
                $query->whereHas('company', fn ($q) => $q->where('trial_started_at', '<=', $cutoff))
                    ->orWhereHas('cfaOrganization', fn ($q) => $q->where('trial_started_at', '<=', $cutoff));
            })
            ->with(['company.user', 'cfaOrganization.user'])
            ->get();

        // Regroupees par proprietaire : une entreprise/CFA avec plusieurs offres
        // archivees en meme temps recoit une notification par offre (comme avant)
        // mais un seul email listant toutes les offres concernees.
        $archivedTitlesByOwnerId = [];
        $ownersById = [];

        $priceLabelByOwnerId = [];

        foreach ($offers as $offer) {
            $offer->update(['status' => JobOfferStatus::ARCHIVED]);

            $owner = $this->jobOfferService->ownerUser($offer);
            if (! $owner) {
                continue;
            }

            $priceLabel = $this->jobOfferService->priceLabelFor($offer);

            $owner->notifications()->create([
                'type' => NotificationType::TRIAL_OFFERS_ARCHIVED,
                'message' => "Ta période d'essai gratuite est terminée, l'offre \"{$offer->title}\" a été archivée. Publie-la à nouveau en payant {$priceLabel} pour la rendre visible.",
                'link' => '/mes-offres',
            ]);

            $ownersById[$owner->id] = $owner;
            $archivedTitlesByOwnerId[$owner->id][] = $offer->title;
            $priceLabelByOwnerId[$owner->id] = $priceLabel;
        }

        foreach ($archivedTitlesByOwnerId as $ownerId => $titles) {
            /** @var User $owner */
            $owner = $ownersById[$ownerId];
            $this->mailService->sendTrialEndedEmail($owner->email, $titles, $priceLabelByOwnerId[$ownerId]);
        }

        $this->info(count($offers)." offre(s) d'essai archivee(s).");

        return self::SUCCESS;
    }
}
