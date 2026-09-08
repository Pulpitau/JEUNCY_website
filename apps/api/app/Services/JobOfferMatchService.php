<?php

namespace App\Services;

use App\Enums\ContractType;
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
    // En dessous de 4 caracteres, un mot du titre ne discrimine plus rien
    // ("un", "de", "web" mis a part, mais le bruit l'emporte largement).
    private const MIN_KEYWORD_LENGTH = 4;

    // Mots trop frequents dans un intitule d'offre pour signifier quoi que ce
    // soit : les retenir ferait correspondre toutes les offres a tous les
    // candidats, et la notification perdrait tout credit.
    private const STOPWORDS = [
        'alternance', 'alternant', 'alternante', 'apprenti', 'apprentie',
        'stage', 'stagiaire', 'contrat', 'poste', 'offre', 'emploi', 'job',
        'recherche', 'recrute', 'recrutons', 'cherche', 'cherchons',
        'temps', 'plein', 'partiel', 'saisonnier', 'saisonniere', 'benevole',
        'debutant', 'debutante', 'junior', 'senior', 'niveau', 'profil',
        'avec', 'sans', 'pour', 'dans', 'chez', 'notre', 'votre', 'nous',
        'vous', 'etre', 'plus', 'tous', 'toute', 'toutes', 'cette',
    ];

    // Previent les candidats correspondants et renvoie leur nombre.
    //
    // Volontairement synchrone : la publication d'une offre est rare et le
    // travail se resume a une requete plus une insertion groupee. Une file
    // d'attente demanderait un worker permanent, impossible sur l'hebergement
    // mutualise actuel (voir CLAUDE.md section 11).
    public function notifyMatchingCandidates(JobOffer $jobOffer): int
    {
        $keywords = $this->keywordsOf($jobOffer);
        $city = $this->normalize((string) $jobOffer->city);

        $notifications = [];

        CandidateProfile::query()
            ->with(['user:id,is_suspended,deleted_account_at', 'skills:id,name', 'software:id,name'])
            // Un candidat deja candidat a cette offre n'a rien a apprendre.
            ->whereDoesntHave('applications', fn ($q) => $q->where('job_offer_id', $jobOffer->id))
            ->chunkById(200, function ($profiles) use ($jobOffer, $keywords, $city, &$notifications) {
                foreach ($profiles as $profile) {
                    if (! $this->isReachable($profile)) {
                        continue;
                    }
                    if (! $this->matches($profile, $jobOffer, $keywords, $city)) {
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

                $correspond = $this->matches(
                    $profile,
                    $offre,
                    $this->keywordsOf($offre),
                    $this->normalize((string) $offre->city),
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

    // Un candidat correspond si l'offre est dans SA ville, ou si son profil
    // partage un mot significatif avec l'intitule de l'offre.
    //
    // Le OU est volontaire : exiger les deux ne notifierait presque personne,
    // et une offre proche geographiquement interesse un jeune meme si
    // l'intitule ne recoupe pas exactement ce qu'il a ecrit — la mobilite est
    // le premier critere a cet age.
    private function matches(CandidateProfile $profile, JobOffer $jobOffer, array $keywords, string $city): bool
    {
        if ($this->contractIsExcluded($profile, $jobOffer)) {
            return false;
        }

        if ($city !== '' && $this->normalize((string) $profile->city) === $city) {
            return true;
        }

        return $this->sharesKeyword($profile, $keywords);
    }

    // Un candidat qui a ecrit explicitement chercher une alternance n'est pas
    // prevenu d'une mission de benevolat, et reciproquement. On n'exclut que
    // sur une mention EXPLICITE : un profil muet reste eligible a tout, sans
    // quoi les candidats qui n'ont pas rempli ce champ ne verraient jamais
    // aucune offre.
    private function contractIsExcluded(CandidateProfile $profile, JobOffer $jobOffer): bool
    {
        $souhait = $this->normalize(($profile->headline ?? '').' '.($profile->bio ?? ''));

        $mentions = [
            ContractType::ALTERNANCE->value => Str::contains($souhait, ['alternance', 'alternant', 'apprentissage', 'apprenti']),
            ContractType::SAISONNIER->value => Str::contains($souhait, ['saisonnier', 'saisonniere', 'job d ete', 'job ete', 'saison']),
            ContractType::BENEVOLAT->value => Str::contains($souhait, ['benevolat', 'benevole', 'volontariat', 'service civique']),
            // "etudiant" seul est volontairement absent : c'est un statut, pas
            // un souhait de contrat. Le laisser ici excluait de toutes les
            // alternances quiconque ecrivait "etudiant en BTS" — soit
            // l'essentiel du public de Jeuncy.
            ContractType::JOB_ETUDIANT->value => Str::contains($souhait, ['job etudiant', 'temps partiel']),
            ContractType::STAGE->value => Str::contains($souhait, ['stage', 'stagiaire']),
        ];

        // Aucune mention : le candidat n'a exprime aucune preference.
        if (! in_array(true, $mentions, true)) {
            return false;
        }

        return ($mentions[$jobOffer->contract_type->value] ?? false) === false;
    }

    private function sharesKeyword(CandidateProfile $profile, array $keywords): bool
    {
        if ($keywords === []) {
            return false;
        }

        $mots = preg_split('/[^\p{L}]+/u', $this->normalize(implode(' ', [
            $profile->headline ?? '',
            $profile->bio ?? '',
            $profile->skills->pluck('name')->implode(' '),
            $profile->software->pluck('name')->implode(' '),
        ]))) ?: [];

        foreach ($keywords as $keyword) {
            foreach ($mots as $mot) {
                if ($this->sameWordFamily($keyword, $mot)) {
                    return true;
                }
            }
        }

        return false;
    }

    // Deux mots de la meme famille : "commerce" et "commercial", "restaurant"
    // et "restauration", "vente" et "ventes".
    //
    // La comparaison etait auparavant litterale, et un candidat decrit sa
    // recherche avec SES mots, jamais avec ceux de l'intitule de l'offre :
    // "alternance en commerce" ne correspondait pas a "assistant commercial".
    //
    // Six caracteres communs, ou l'un prefixe l'autre a partir de cinq
    // caracteres (pour les pluriels). En dessous, "vent" rapprocherait
    // "ventilateur" — et une notification hors sujet coute plus cher qu'une
    // notification manquante.
    private const MIN_COMMON_PREFIX = 6;

    private const MIN_PREFIX_WORD_LENGTH = 5;

    private function sameWordFamily(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }

        $court = min(mb_strlen($a), mb_strlen($b));

        if ($court < self::MIN_PREFIX_WORD_LENGTH) {
            return false;
        }

        $commun = 0;
        while ($commun < $court && mb_substr($a, $commun, 1) === mb_substr($b, $commun, 1)) {
            $commun++;
        }

        return $commun >= min(self::MIN_COMMON_PREFIX, $court);
    }

    /** @return string[] */
    private function keywordsOf(JobOffer $jobOffer): array
    {
        $mots = preg_split('/[^\p{L}]+/u', $this->normalize($jobOffer->title)) ?: [];

        return array_values(array_unique(array_filter(
            $mots,
            fn (string $mot) => mb_strlen($mot) >= self::MIN_KEYWORD_LENGTH
                && ! in_array($mot, self::STOPWORDS, true),
        )));
    }

    // Comparaison sans accents ni casse : "Perpignan" et "PERPIGNAN" designent
    // la meme ville, "developpeur" et "développeur" le meme metier.
    private function normalize(string $value): string
    {
        return Str::lower(Str::ascii(trim($value)));
    }
}
