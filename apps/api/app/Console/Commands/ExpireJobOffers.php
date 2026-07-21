<?php

namespace App\Console\Commands;

use App\Enums\JobOfferStatus;
use App\Models\JobOffer;
use Illuminate\Console\Command;

class ExpireJobOffers extends Command
{
    protected $signature = 'job-offers:expire';

    protected $description = "Passe au statut EXPIRED les offres publiees dont la date d'expiration est depassee";

    public function handle(): int
    {
        $count = JobOffer::query()
            ->where('status', JobOfferStatus::PUBLISHED)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => JobOfferStatus::EXPIRED]);

        $this->info("{$count} offre(s) passee(s) au statut EXPIRED.");

        return self::SUCCESS;
    }
}
