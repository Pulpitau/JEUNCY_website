<?php

namespace App\Console\Commands;

use App\Models\CandidateProfile;
use App\Services\JobOfferMatchService;
use Illuminate\Console\Command;

/**
 * Balayage de rattrapage : previent les candidats des offres publiees qui leur
 * correspondent et dont ils n'ont jamais entendu parler.
 *
 * POURQUOI CETTE TACHE EXISTE. La notification part a la publication d'une
 * offre (JobOfferMatchService::notifyMatchingCandidates) et a la creation ou
 * modification d'un profil (CandidateProfileService). Restent les candidats
 * inscrits AVANT que ce second sens n'existe, et ceux qui ne touchent plus a
 * leur profil : sans ce balayage, ils ne verraient jamais les offres deja en
 * ligne. Constate en production le 2026-09-04, avec 54 profils et une offre
 * publiee que personne parmi eux n'avait vue.
 *
 * Idempotente : notifyCandidateOfMatchingOffers dedoublonne sur les
 * notifications deja envoyees, donc une offre n'est annoncee qu'une fois a un
 * candidat donne, quel que soit le nombre d'executions.
 */
class NotifyCandidatesOfMatchingOffers extends Command
{
    protected $signature = 'job-offers:notify-matching-candidates';

    protected $description = 'Previent les candidats des offres publiees qui leur correspondent et qu ils n ont pas encore vues';

    public function handle(JobOfferMatchService $matchService): int
    {
        $candidats = 0;
        $notifications = 0;

        // Par lots : le traitement est synchrone (pas de worker possible sur
        // l'hebergement mutualise) et doit tenir dans le temps d'execution
        // d'un cron, meme quand la CVtheque aura grossi.
        CandidateProfile::query()
            ->with(['user:id,is_suspended,deleted_account_at', 'skills:id,name', 'software:id,name'])
            ->chunkById(100, function ($profils) use ($matchService, &$candidats, &$notifications) {
                foreach ($profils as $profil) {
                    $envoyees = $matchService->notifyCandidateOfMatchingOffers($profil);
                    $notifications += $envoyees;
                    if ($envoyees > 0) {
                        $candidats++;
                    }
                }
            });

        $this->info("{$notifications} notification(s) envoyee(s) a {$candidats} candidat(s).");

        return self::SUCCESS;
    }
}
