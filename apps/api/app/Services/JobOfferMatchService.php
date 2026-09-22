<?php

namespace App\Services;

use App\Enums\JobOfferStatus;
use App\Enums\NotificationType;
use App\Models\CandidateProfile;
use App\Models\JobOffer;
use App\Models\Notification;
use Illuminate\Support\Str;

// Previent les candidats dont le profil correspond a une offre qui vient
// d'etre publiee.
//
// POURQUOI CE SERVICE EXISTE. La demande initiale etait de faire postuler
// AUTOMATIQUEMENT les candidats correspondants des qu'une entreprise paie sa
// publication. C'est techniquement trivial, mais une candidature signifie "je
// veux ce poste" : l'envoyer a la place du candidat lui fait dire ce qu'il n'a
// pas dit. L'entreprise — celle qui paie — appelle alors des gens qui n'ont
// rien demande, et le candidat recoit des appels pour des postes qu'il n'a pas
// choisis. Les deux cotes y perdent.
//
// La notification donne la meme reactivite commerciale (l'entreprise recoit
// ses premieres candidatures dans l'heure) avec de vraies candidatures. Le
// candidat arrive sur l'offre ou son telephone et son CV sont deja
// pre-remplis : postuler tient effectivement en un clic.
class JobOfferMatchService
{
    // La regle de correspondance elle-meme vit dans MatchScorer depuis le
    // 2026-09-22 : la pile Decouvrir (DiscoverService) l'applique aussi, et
    // deux copies divergeraient. Ce service garde ce qui lui est propre —
    // qui prevenir, avec quel message, sans doublon.
    public function __construct(private readonly MatchScorer $scorer) {}

    // Previent les candidats correspondants et renvoie leur nombre.
    //
    // Volontairement synchrone : la publication d'une offre est rare et le
    // travail se resume a une requete plus une insertion groupee. Une file
    // d'attente demanderait un worker permanent, impossible sur l'hebergement
    // mutualise actuel (voir CLAUDE.md section 11).
    public function notifyMatchingCandidates(JobOffer $jobOffer): int
    {
        $keywords = $this->scorer->keywordsOf($jobOffer);
        $city = $this->scorer->normalize((string) $jobOffer->city);

        $notifications = [];

        // Une offre remise en ligne chaque mois ne doit pas renvoyer la
        // meme annonce aux memes jeunes mois apres mois : on ecarte ceux
        // qui portent deja une notification pour cette offre. C'est la
        // regle qu'applique deja notifyCandidateOfMatchingOffers dans
        // l'autre sens ; les deux directions se comportent enfin pareil.
        $dejaPrevenus = Notification::query()
            ->where('type', NotificationType::JOB_OFFER_MATCH->value)
            ->where('link', '/offres/'.$jobOffer->id)
            ->pluck('user_id')
            ->all();

        CandidateProfile::query()
            ->with(['user:id,is_suspended,deleted_account_at', 'skills:id,name', 'software:id,name'])
            // Un candidat deja candidat a cette offre n'a rien a apprendre.
            ->whereDoesntHave('applications', fn ($q) => $q->where('job_offer_id', $jobOffer->id))
            ->whereNotIn('user_id', $dejaPrevenus ?: [0])
            ->chunkById(200, function ($profiles) use ($jobOffer, $keywords, $city, &$notifications) {
                foreach ($profiles as $profile) {
                    if (! $this->isReachable($profile)) {
                        continue;
                    }
                    if (! $this->scorer->matches($profile, $jobOffer, $keywords, $city)) {
                        continue;
                    }

                    $notifications[] = [
                        'user_id' => $profile->user_id,
                        'type' => NotificationType::JOB_OFFER_MATCH->value,
                        'message' => $this->messageFor($jobOffer),
                        'link' => '/offres/'.$jobOffer->id,
                        'read' => false,
                        // Insertion groupee : Eloquent ne remplit pas les dates
                        // ici, et la table ne porte que created_at
                        // (Notification::$timestamps est a false).
                        'created_at' => now(),
                    ];
                }
            });

        foreach (array_chunk($notifications, 200) as $batch) {
            Notification::insert($batch);
        }

        return count($notifications);
    }

    // Au plus trois offres annoncees d'un coup. Quelqu'un qui vient de
    // completer son profil peut correspondre a beaucoup d'offres : lui en
    // envoyer quinze d'affilee serait du harcelement, et il a de toute facon
    // la liste complete sous les yeux. Les plus recentes d'abord.
    private const MAX_OFFERS_PER_CANDIDATE = 3;

    /**
     * Previent un candidat des offres deja publiees qui lui correspondent.
     *
     * Symetrique de notifyMatchingCandidates(), qui ne couvre que le moment de
     * la publication. Sans ce sens-ci, un candidat inscrit apres la mise en
     * ligne d'une offre n'en entend jamais parler — le cas le plus frequent
     * quand on remplit la CVtheque par prospection telephonique.
     *
     * Une offre n'est annoncee qu'UNE fois a un candidat donne : la
     * deduplication porte sur les notifications deja envoyees, ce qui rend la
     * methode sans danger a chaque modification de profil.
     */
    public function notifyCandidateOfMatchingOffers(CandidateProfile $profile): int
    {
        $profile->loadMissing(['user', 'skills:id,name', 'software:id,name']);

        if (! $this->isReachable($profile)) {
            return 0;
        }

        $dejaVues = Notification::query()
            ->where('user_id', $profile->user_id)
            ->where('type', NotificationType::JOB_OFFER_MATCH->value)
            ->pluck('link')
            ->all();

        $dejaCandidat = $profile->applications()->pluck('job_offer_id')->all();

        $notifications = [];

        JobOffer::query()
            ->where('status', JobOfferStatus::PUBLISHED)
            ->whereNotIn('id', $dejaCandidat ?: [0])
            ->latest('published_at')
            ->limit(200)
            ->get()
            ->each(function (JobOffer $offre) use ($profile, $dejaVues, &$notifications) {
                if (count($notifications) >= self::MAX_OFFERS_PER_CANDIDATE) {
                    return false;
                }

                $lien = '/offres/'.$offre->id;
                if (in_array($lien, $dejaVues, true)) {
                    return null;
                }

                $correspond = $this->scorer->matches(
                    $profile,
                    $offre,
                    $this->scorer->keywordsOf($offre),
                    $this->scorer->normalize((string) $offre->city),
                );

                if ($correspond) {
                    $notifications[] = [
                        'user_id' => $profile->user_id,
                        'type' => NotificationType::JOB_OFFER_MATCH->value,
                        'message' => $this->messageForExistingOffer($offre),
                        'link' => $lien,
                        'read' => false,
                        'created_at' => now(),
                    ];
                }

                return null;
            });

        if ($notifications !== []) {
            Notification::insert($notifications);
        }

        return count($notifications);
    }

    private function messageFor(JobOffer $jobOffer): string
    {
        return "Une offre qui te correspond vient d'être publiée : « "
            .Str::limit($jobOffer->title, 70)
            .' ». Postule en un clic !';
    }

    // Dans l'autre sens, l'offre n'est pas nouvelle : c'est le candidat qui
    // vient d'arriver. Lui annoncer une publication "qui vient d'avoir lieu"
    // serait faux, et il s'en apercevrait en voyant la date de l'offre.
    private function messageForExistingOffer(JobOffer $jobOffer): string
    {
        return 'Une offre correspond à ton profil : « '
            .Str::limit($jobOffer->title, 70)
            .' ». Postule en un clic !';
    }

    // Un compte suspendu ou supprime ne doit rien recevoir : sa notification
    // ne serait jamais lue, et pour un compte supprime elle rattacherait de
    // l'activite a une identite anonymisee.
    private function isReachable(CandidateProfile $profile): bool
    {
        $user = $profile->user;

        return $user !== null && ! $user->is_suspended && $user->deleted_account_at === null;
    }
}
