<?php

namespace App\Services;

use App\Enums\ContractType;
use App\Models\CandidateProfile;
use App\Models\JobOffer;
use Illuminate\Support\Str;

// Regle de correspondance entre un profil candidat et une offre Jeuncy,
// extraite telle quelle de JobOfferMatchService (2026-09-03) pour etre
// partagee par la notification « une offre te correspond » et par le tri de
// la pile Decouvrir (DiscoverService).
//
// POURQUOI UNE EXTRACTION PLUTOT QU'UNE SECONDE REGLE. Deux regles de
// correspondance divergeraient en un mois : un candidat serait prevenu d'une
// offre que sa pile ne lui montre pas, ou l'inverse. Les noms de methodes et
// les constantes sont repris a l'identique, et un seul comportement change
// (voir contractIsExcluded).
class MatchScorer
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

    // Deux mots de la meme famille : "commerce" et "commercial", "restaurant"
    // et "restauration", "vente" et "ventes".
    //
    // Six caracteres communs, ou l'un prefixe l'autre a partir de cinq
    // caracteres (pour les pluriels). En dessous, "vent" rapprocherait
    // "ventilateur" — et une notification hors sujet coute plus cher qu'une
    // notification manquante.
    private const MIN_COMMON_PREFIX = 6;

    private const MIN_PREFIX_WORD_LENGTH = 5;

    // Poids du tri de la pile candidat. La ville pese plus que le metier : la
    // mobilite est le premier critere a cet age (meme raison que le OU de
    // matches()).
    private const POIDS_VILLE = 2;

    private const POIDS_MOT_PARTAGE = 1;

    private const POIDS_SECTEUR = 1;

    // Un candidat correspond si l'offre est dans SA ville, ou si son profil
    // partage un mot significatif avec l'intitule de l'offre.
    //
    // Le OU est volontaire : exiger les deux ne notifierait presque personne,
    // et une offre proche geographiquement interesse un jeune meme si
    // l'intitule ne recoupe pas exactement ce qu'il a ecrit.
    public function matches(CandidateProfile $profile, JobOffer $jobOffer, array $keywords, string $city): bool
    {
        if ($this->contractIsExcluded($profile, $jobOffer)) {
            return false;
        }

        if ($city !== '' && $this->normalize((string) $profile->city) === $city) {
            return true;
        }

        return $this->sharesKeyword($profile, $keywords);
    }

    /**
     * Note de pertinence d'une offre pour un candidat, servant a ORDONNER la
     * pile Decouvrir — jamais a filtrer : une offre a portee reste montree
     * meme avec un score de zero, sinon un candidat au profil peu rempli
     * verrait une pile vide.
     *
     * Zero quand le contrat est exclu : c'est le seul critere qui vaut un
     * refus (le candidat a dit explicitement chercher autre chose).
     */
    public function score(CandidateProfile $profile, JobOffer $jobOffer): int
    {
        if ($this->contractIsExcluded($profile, $jobOffer)) {
            return 0;
        }

        $score = 0;

        $city = $this->normalize((string) $jobOffer->city);
        if ($city !== '' && $this->normalize((string) $profile->city) === $city) {
            $score += self::POIDS_VILLE;
        }

        if ($this->sharesKeyword($profile, $this->keywordsOf($jobOffer))) {
            $score += self::POIDS_MOT_PARTAGE;
        }

        $secteurs = array_map(fn ($secteur) => $secteur->value, $profile->wantedSectors());
        if ($jobOffer->sector !== null && in_array($jobOffer->sector->value, $secteurs, true)) {
            $score += self::POIDS_SECTEUR;
        }

        return $score;
    }

    /**
     * Un candidat qui a ecrit explicitement chercher une alternance n'est pas
     * prevenu d'une mission de benevolat, et reciproquement. On n'exclut que
     * sur une mention EXPLICITE : un profil muet reste eligible a tout, sans
     * quoi les candidats qui n'ont pas rempli ce champ ne verraient jamais
     * aucune offre.
     *
     * SEUL CHANGEMENT DE REGLE de l'extraction : quand le candidat a rempli
     * wanted_contract_types (ecran « Ce que je cherche », MOBILE.md §3.1), sa
     * reponse structuree remplace l'heuristique textuelle. Une case cochee
     * est une reponse a la question posee ; deviner le meme souhait dans le
     * titre et la bio est un pis-aller qui ne sert que pour les profils
     * anterieurs a cet ecran, lesquels gardent exactement l'ancien
     * comportement.
     */
    public function contractIsExcluded(CandidateProfile $profile, JobOffer $jobOffer): bool
    {
        $souhaites = $profile->wantedContractTypes();

        if ($souhaites !== []) {
            return ! in_array($jobOffer->contract_type, $souhaites, true);
        }

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

    public function sharesKeyword(CandidateProfile $profile, array $keywords): bool
    {
        if ($keywords === []) {
            return false;
        }

        $profile->loadMissing(['skills:id,name', 'software:id,name']);

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

    /** @return string[] */
    public function keywordsOf(JobOffer $jobOffer): array
    {
        $mots = preg_split('/[^\p{L}]+/u', $this->normalize((string) $jobOffer->title)) ?: [];

        return array_values(array_unique(array_filter(
            $mots,
            fn (string $mot) => mb_strlen($mot) >= self::MIN_KEYWORD_LENGTH
                && ! in_array($mot, self::STOPWORDS, true),
        )));
    }

    // Comparaison sans accents ni casse : "Perpignan" et "PERPIGNAN" designent
    // la meme ville, "developpeur" et "développeur" le meme metier.
    public function normalize(string $value): string
    {
        return Str::lower(Str::ascii(trim($value)));
    }

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
}
