<?php

namespace App\Services;

use App\Enums\InterestDecision;
use App\Enums\MatchClosedReason;
use App\Enums\MatchReminderStage;
use App\Enums\NotificationType;
use App\Models\Application;
use App\Models\OfferInterest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Relances du modele match (MOBILE.md §5).
 *
 * C'EST LA PROMESSE DU PRODUIT, pas une politesse. Les « swipe de l'emploi »
 * qui ont echoue sont morts d'employeurs muets et de piles vides : le seul
 * argument que Jeuncy peut tenir est « tu auras une reponse ». Une cascade
 * de relances est la forme concrete de cet argument — sans elle, la promesse
 * n'est qu'une phrase sur une page d'accueil.
 *
 * QUATRE CASCADES, QUATRE SILENCES DIFFERENTS :
 *
 *   1. l'employeur a dit oui, le candidat se tait  -> J+3, expire J+14
 *   2. le candidat a dit oui, l'employeur se tait  -> J+7, expire J+14
 *   3. match sans dossier                          -> J+2, J+7, expire J+30
 *   4. dossier sans reponse                        -> J+3, J+7, J+14, J+30
 *
 * IDEMPOTENCE. Le cron d'OVH est horaire et saute des passages. Chaque ligne
 * porte donc l'etage atteint (`reminder_stage`) : une passe manquee repart au
 * passage suivant, une passe rejouee ne renvoie rien. Sans cette colonne, un
 * seul cron rejoue enverrait deux fois le meme rappel a un vrai candidat.
 *
 * CE QUE LA CASCADE NE FAIT JAMAIS :
 *   - reveler a un employeur qu'un candidat s'est interesse a lui (etage 2) :
 *     il ne l'a jamais su, une relance nominative le lui apprendrait et
 *     montrerait un geste que le produit avait garde pour lui ;
 *   - poser un statut de candidature au nom de l'entreprise (etage 4, J+30) :
 *     Jeuncy peut dire « on n'a pas eu de reponse », pas « vous etes refuse ».
 */
class MatchReminderService
{
    /** Nombre de lignes traitees par passage, toutes cascades confondues. */
    public const LOT_MAX = 200;

    public function __construct(
        private readonly JobOfferService $jobOfferService,
        private readonly MailService $mailService,
    ) {}

    /**
     * Une passe complete. Renvoie le compte par etage, pour le journal de la
     * commande et pour la sonde de deploiement.
     *
     * @return array<string, int>
     */
    public function run(): array
    {
        $compte = [];

        foreach ($this->relancesInteret() as $etage => $n) {
            $compte[$etage] = ($compte[$etage] ?? 0) + $n;
        }
        foreach ($this->relancesDossier() as $etage => $n) {
            $compte[$etage] = ($compte[$etage] ?? 0) + $n;
        }

        return $compte;
    }

    // -----------------------------------------------------------------
    // Cascades 1 a 3 : ce qui vit sur offer_interests
    // -----------------------------------------------------------------

    /**
     * @return array<string, int>
     */
    private function relancesInteret(): array
    {
        $compte = [];

        $lignes = OfferInterest::query()
            ->whereNull('closed_at')
            ->where(fn ($q) => $q
                ->whereNull('reminder_stage')
                ->orWhereNotIn('reminder_stage', $this->etagesFinaux()))
            ->with(['jobOffer.company', 'jobOffer.cfaOrganization', 'candidateProfile.user'])
            ->orderBy('id')
            ->limit(self::LOT_MAX)
            ->get();

        foreach ($lignes as $ligne) {
            $etage = $this->etageAttendu($ligne);

            if ($etage === null || $etage->value === $ligne->reminder_stage) {
                continue;
            }

            $this->appliquer($ligne, $etage);
            $compte[$etage->value] = ($compte[$etage->value] ?? 0) + 1;
        }

        return $compte;
    }

    /**
     * L'etage que cette ligne DEVRAIT avoir atteint, vu son age.
     *
     * Renvoie le plus avance des etages dus, pas le suivant : une ligne
     * oubliee trois semaines doit finir expiree en un passage, pas recevoir
     * un rappel de J+3 trois semaines trop tard.
     */
    private function etageAttendu(OfferInterest $ligne): ?MatchReminderStage
    {
        // Cascade 3 : les deux ont dit oui, le dossier n'est pas parti.
        if ($ligne->matched_at !== null && $ligne->application_id === null) {
            $jours = $ligne->matched_at->diffInDays(now());

            return match (true) {
                $jours >= 30 => MatchReminderStage::MATCH_NO_APPLICATION_EXPIRED,
                $jours >= 7 => MatchReminderStage::MATCH_NO_APPLICATION_D7,
                $jours >= 2 => MatchReminderStage::MATCH_NO_APPLICATION_D2,
                default => null,
            };
        }

        // Un match avec dossier ne releve plus de cette table : la suite se
        // joue sur la candidature (cascade 4).
        if ($ligne->matched_at !== null) {
            return null;
        }

        // Cascade 1 : l'employeur a dit oui, le candidat n'a rien repondu.
        if ($ligne->employer_decision === InterestDecision::LIKE
            && $ligne->candidate_decision === null) {
            $jours = $ligne->employer_decided_at?->diffInDays(now()) ?? 0;

            return match (true) {
                $jours >= 14 => MatchReminderStage::EMPLOYER_INTEREST_EXPIRED,
                $jours >= 3 => MatchReminderStage::EMPLOYER_INTEREST_D3,
                default => null,
            };
        }

        // Cascade 2 : le candidat a dit oui, l'employeur n'a rien repondu.
        if ($ligne->candidate_decision === InterestDecision::LIKE
            && $ligne->employer_decision === null) {
            $jours = $ligne->candidate_decided_at?->diffInDays(now()) ?? 0;

            return match (true) {
                $jours >= 14 => MatchReminderStage::CANDIDATE_INTEREST_EXPIRED,
                $jours >= 7 => MatchReminderStage::CANDIDATE_INTEREST_D7,
                default => null,
            };
        }

        return null;
    }

    private function appliquer(OfferInterest $ligne, MatchReminderStage $etage): void
    {
        $offre = $ligne->jobOffer;
        $candidat = $ligne->candidateProfile?->user;

        if ($offre === null) {
            // L'offre a disparu sans passer par MatchClosingService : on
            // ferme la ligne plutot que de la relancer dans le vide.
            $this->fermer($ligne, $etage, MatchClosedReason::EXPIRED);

            return;
        }

        $titre = $offre->title;
        $organisation = $offre->company?->name ?? $offre->cfaOrganization?->name ?? 'Un recruteur';

        match ($etage) {
            MatchReminderStage::EMPLOYER_INTEREST_D3 => $this->prevenirCandidat(
                $candidat,
                "{$organisation} s'intéresse à toi pour « {$titre} ». Réponds avant qu'elle ne passe à quelqu'un d'autre.",
                '/offres/'.$offre->id,
                'Une entreprise t’attend',
                'Voir l’offre',
            ),
            MatchReminderStage::CANDIDATE_INTEREST_D7 => $this->prevenirCandidat(
                $candidat,
                "Pas encore de réponse pour « {$titre} ». Tu peux envoyer ton dossier directement : c'est souvent ce qui débloque.",
                '/offres/'.$offre->id,
                'Envoie ton dossier',
                'Postuler',
            ),
            MatchReminderStage::MATCH_NO_APPLICATION_D2,
            MatchReminderStage::MATCH_NO_APPLICATION_D7 => $this->prevenirCandidat(
                $candidat,
                "{$organisation} attend ton dossier pour « {$titre} ». Sans lui, l'entreprise ne voit que ta carte.",
                '/mes-candidatures',
                'Ton dossier t’attend',
                'Envoyer mon dossier',
            ),
            // Les trois expirations ne s'annoncent qu'au candidat, et
            // seulement quand il y avait un match : un interet a sens unique
            // n'avait ete annonce a personne (meme regle que
            // MatchClosingService).
            MatchReminderStage::EMPLOYER_INTEREST_EXPIRED,
            MatchReminderStage::CANDIDATE_INTEREST_EXPIRED,
            MatchReminderStage::MATCH_NO_APPLICATION_EXPIRED => $this->fermer(
                $ligne,
                $etage,
                MatchClosedReason::EXPIRED,
                $ligne->matched_at !== null ? $candidat : null,
                "La mise en relation pour « {$titre} » s'est terminée faute de réponse.",
            ),
            default => null,
        };

        if (! $etage->isFinal()) {
            $this->marquer($ligne, $etage);
        }
    }

    // -----------------------------------------------------------------
    // Cascade 4 : le dossier sans reponse
    // -----------------------------------------------------------------

    /**
     * @return array<string, int>
     */
    private function relancesDossier(): array
    {
        $compte = [];

        $dossiers = Application::query()
            ->whereNull('responded_at')
            ->where(fn ($q) => $q
                ->whereNull('reminder_stage')
                ->orWhere('reminder_stage', '!=', MatchReminderStage::APPLICATION_SILENT_CLOSED->value))
            ->with(['jobOffer.company', 'jobOffer.cfaOrganization', 'candidateProfile.user'])
            ->orderBy('id')
            ->limit(self::LOT_MAX)
            ->get();

        foreach ($dossiers as $dossier) {
            $jours = $dossier->created_at?->diffInDays(now()) ?? 0;

            $etage = match (true) {
                $jours >= 30 => MatchReminderStage::APPLICATION_SILENT_CLOSED,
                $jours >= 14 => MatchReminderStage::APPLICATION_SILENT_D14,
                $jours >= 7 => MatchReminderStage::APPLICATION_SILENT_D7,
                $jours >= 3 => MatchReminderStage::APPLICATION_SILENT_D3,
                default => null,
            };

            if ($etage === null || $etage->value === $dossier->reminder_stage) {
                continue;
            }

            $this->appliquerDossier($dossier, $etage);
            $compte[$etage->value] = ($compte[$etage->value] ?? 0) + 1;
        }

        return $compte;
    }

    private function appliquerDossier(Application $dossier, MatchReminderStage $etage): void
    {
        $offre = $dossier->jobOffer;
        $candidat = $dossier->candidateProfile?->user;
        $employeur = $offre === null ? null : $this->jobOfferService->ownerUser($offre);
        $titre = $offre?->title ?? 'une offre';

        match ($etage) {
            MatchReminderStage::APPLICATION_SILENT_D3 => $this->prevenirEmployeur(
                $employeur,
                "Une candidature attend ta réponse sur « {$titre} ». Un statut, même « Refusée », vaut mieux que le silence.",
                '/mes-offres',
                'Une candidature attend',
                'Répondre',
            ),
            // J+7 : le candidat est prevenu que Jeuncy a vu le silence, et
            // l'employeur entre dans l'onglet admin « Employeurs silencieux »
            // — qui se lit directement sur reminder_stage, sans table de plus.
            MatchReminderStage::APPLICATION_SILENT_D7 => $this->prevenirCandidat(
                $candidat,
                "Pas encore de réponse pour « {$titre} ». On a relancé l'entreprise de notre côté.",
                '/mes-candidatures',
                'On relance pour toi',
                'Voir ma candidature',
            ),
            MatchReminderStage::APPLICATION_SILENT_D14 => $this->prevenirCandidat(
                $candidat,
                "Toujours pas de réponse pour « {$titre} ». Jeuncy a relancé l'entreprise une seconde fois.",
                '/mes-candidatures',
                'On a relancé',
                'Voir ma candidature',
            ),
            MatchReminderStage::APPLICATION_SILENT_CLOSED => $this->cloturerParJeuncy($dossier, $titre, $candidat),
            default => null,
        };

        $dossier->reminder_stage = $etage->value;
        $dossier->reminded_at = now();
        $dossier->saveQuietly();
    }

    /**
     * J+30 : Jeuncy ferme la mise en relation et le dit au candidat.
     *
     * Le statut de la candidature n'est PAS touche : il reste SENT. Poser
     * « Refusée » reviendrait a parler au nom de l'entreprise, qui n'a
     * jamais rien dit — et a inscrire dans l'historique du candidat un refus
     * que personne n'a prononce.
     */
    private function cloturerParJeuncy(Application $dossier, string $titre, ?User $candidat): void
    {
        $ligne = OfferInterest::query()
            ->where('application_id', $dossier->id)
            ->whereNull('closed_at')
            ->first();

        if ($ligne !== null) {
            $ligne->closed_at = now();
            $ligne->closed_reason = MatchClosedReason::CLOSED_BY_STAFF;
            $ligne->saveQuietly();
        }

        $this->prevenirCandidat(
            $candidat,
            "Sans réponse depuis un mois, on clôture la mise en relation pour « {$titre} ». Ce n'est pas un refus : l'entreprise n'a jamais répondu. Continue avec les autres offres.",
            '/mes-candidatures',
            'Clôture par Jeuncy',
            'Voir mes candidatures',
        );
    }

    // -----------------------------------------------------------------
    // Envoi
    // -----------------------------------------------------------------

    private function prevenirCandidat(
        ?User $candidat,
        string $message,
        string $lien,
        string $titreEmail,
        string $cta,
    ): void {
        $this->notifier($candidat, $message, $lien, $titreEmail, $cta);
    }

    private function prevenirEmployeur(
        ?User $employeur,
        string $message,
        string $lien,
        string $titreEmail,
        string $cta,
    ): void {
        $this->notifier($employeur, $message, $lien, $titreEmail, $cta);
    }

    private function notifier(
        ?User $destinataire,
        string $message,
        string $lien,
        string $titreEmail,
        string $cta,
    ): void {
        if ($destinataire === null) {
            return;
        }

        $destinataire->notifications()->create([
            'type' => NotificationType::MATCH_REMINDER,
            'message' => $message,
            'link' => $lien,
        ]);

        if (! $destinataire->email) {
            return;
        }

        $base = rtrim((string) config('app.frontend_url'), '/');

        // Meme filet que MatchService : la relance est deja enregistree quand
        // on arrive ici, un echec d'email ne doit pas la faire disparaitre —
        // ni interrompre la passe pour les 199 autres lignes.
        try {
            $this->mailService->sendMatchReminderEmail(
                to: $destinataire->email,
                subject: $titreEmail,
                heading: $titreEmail,
                message: $message,
                cta: $cta,
                url: $base.$lien,
            );
        } catch (\Throwable $e) {
            Log::error("Echec d'envoi (relance a {$destinataire->email}) : {$e->getMessage()}");
        }
    }

    private function fermer(
        OfferInterest $ligne,
        MatchReminderStage $etage,
        MatchClosedReason $raison,
        ?User $prevenir = null,
        string $message = '',
    ): void {
        DB::transaction(function () use ($ligne, $etage, $raison) {
            $ligne->closed_at = now();
            $ligne->closed_reason = $raison;
            $ligne->reminder_stage = $etage->value;
            $ligne->reminded_at = now();
            $ligne->saveQuietly();
        });

        if ($prevenir !== null && $message !== '') {
            $this->notifier($prevenir, $message, '/mes-candidatures', 'Mise en relation terminée', 'Voir mes candidatures');
        }
    }

    private function marquer(OfferInterest $ligne, MatchReminderStage $etage): void
    {
        $ligne->reminder_stage = $etage->value;
        $ligne->reminded_at = now();
        $ligne->saveQuietly();
    }

    /**
     * @return list<string>
     */
    private function etagesFinaux(): array
    {
        return array_values(array_map(
            fn (MatchReminderStage $etage) => $etage->value,
            array_filter(MatchReminderStage::cases(), fn (MatchReminderStage $e) => $e->isFinal()),
        ));
    }
}
