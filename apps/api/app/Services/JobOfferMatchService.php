<?php

namespace App\Services;

use App\Enums\ContractType;
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

    private function messageFor(JobOffer $jobOffer): string
    {
        return "Une offre qui te correspond vient d'être publiée : « "
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
            ContractType::JOB_ETUDIANT->value => Str::contains($souhait, ['job etudiant', 'etudiant', 'temps partiel']),
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

        $haystack = $this->normalize(implode(' ', [
            $profile->headline ?? '',
            $profile->bio ?? '',
            $profile->skills->pluck('name')->implode(' '),
            $profile->software->pluck('name')->implode(' '),
        ]));

        foreach ($keywords as $keyword) {
            if (Str::contains($haystack, $keyword)) {
                return true;
            }
        }

        return false;
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
