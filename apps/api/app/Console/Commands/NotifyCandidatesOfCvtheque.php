<?php

namespace App\Console\Commands;

use App\Models\CandidateProfile;
use App\Services\MailService;
use Illuminate\Console\Command;

// Informe les candidats DEJA inscrits que la CVtheque est ouverte aux
// recruteurs abonnes (voir MailService::sendCvthequeAnnouncementEmail).
//
// Commande manuelle, JAMAIS planifiee : c'est une annonce ponctuelle liee a
// l'ouverture de la CVtheque le 2026-08-17, pas un envoi recurrent. La mettre
// dans le scheduler la transformerait en spam.
class NotifyCandidatesOfCvtheque extends Command
{
    protected $signature = 'candidates:notify-cvtheque
                            {--send : Envoie reellement les emails (sans cette option, simple apercu)}';

    protected $description = "Informe une seule fois les candidats deja inscrits de l'ouverture de la CVtheque";

    public function handle(MailService $mailService): int
    {
        // Deux conditions cumulees, et c'est volontaire :
        //  - jamais notifie (la colonne porte la garantie d'unicite) ;
        //  - profil cree AVANT l'ouverture — les candidats inscrits depuis ont
        //    vu la mention sur le formulaire d'inscription, les relancer par
        //    email n'aurait aucun sens.
        $profiles = CandidateProfile::query()
            ->whereNull('cvtheque_notified_at')
            ->where('created_at', '<', config('services.cvtheque.launched_at'))
            ->with('user:id,email')
            ->get();

        if ($profiles->isEmpty()) {
            $this->info('Aucun candidat a informer : soit ils l\'ont deja ete, soit ils se sont inscrits apres l\'ouverture de la CVtheque.');

            return self::SUCCESS;
        }

        // Apercu par defaut : un envoi de masse ne doit pas pouvoir partir sur
        // une faute de frappe. Il faut passer --send explicitement.
        if (! $this->option('send')) {
            $this->info("{$profiles->count()} candidat(s) seraient informe(s) :");
            foreach ($profiles as $profile) {
                $this->line("  - {$profile->user->email} ({$profile->first_name} {$profile->last_name})");
            }
            $this->newLine();
            $this->comment('Apercu uniquement. Relance avec --send pour envoyer reellement.');

            return self::SUCCESS;
        }

        $sent = 0;
        foreach ($profiles as $profile) {
            if (! $profile->user?->email) {
                continue;
            }

            $mailService->sendCvthequeAnnouncementEmail($profile->user->email, $profile->first_name);

            // Marque APRES l'envoi, un profil a la fois : si la commande est
            // interrompue en cours de route (timeout, coupure), les candidats
            // deja traites ne seront pas resollicites au relancement.
            $profile->update(['cvtheque_notified_at' => now()]);
            $sent++;
        }

        $this->info("{$sent} candidat(s) informe(s) de l'ouverture de la CVtheque.");

        return self::SUCCESS;
    }
}
