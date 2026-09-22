<?php

namespace App\Services;

use App\Enums\ExternalInterestDecision;
use App\Enums\ExternalJobOfferStatus;
use App\Enums\InterestDecision;
use App\Enums\JobOfferStatus;
use App\Exceptions\ApiException;
use App\Models\CandidateProfile;
use App\Models\ExternalInterest;
use App\Models\ExternalJobOffer;
use App\Models\JobOffer;
use App\Models\OfferInterest;
use App\Models\User;
use App\Presenters\CandidateCardPresenter;
use App\Support\Haversine;
use App\Support\MatchPerimeter;
use App\Support\PostalCodes;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Les deux piles de Decouvrir (MOBILE.md §3 et §4) : les offres montrees a un
 * candidat, les candidats montres a un employeur pour une offre donnee.
 *
 * Ce service ne fait que LIRE et ORDONNER. Les gestes (« Ca m'interesse »,
 * « Passer ») sont ecrits par InterestService, qui s'appuie ici pour les
 * quotas et pour l'eligibilite d'une cible — dans ce sens uniquement, pour
 * qu'il n'y ait jamais de dependance circulaire entre les deux.
 *
 * DEUX ASYMETRIES VOULUES, qui sont la raison d'etre du fichier :
 *  - la pile du candidat n'est JAMAIS bornee au perimetre departemental
 *    (JEUNCY_MATCH_DEPARTEMENTS ne ferme que le cote employeur) : un jeune
 *    de Rennes doit pouvoir s'interesser a une offre ou qu'elle soit ;
 *  - la pile de l'employeur ne porte NI distance NI score : le lieu de
 *    residence n'est pas un critere de selection licite (L1132-1), donc il
 *    ne sort pas, et rien n'est trie par proximite. La distance ne sert
 *    qu'a filtrer ce que les deux parties ont elles-memes declare accepter.
 */
class DiscoverService
{
    // Taille d'une pile rendue au client.
    public const TAILLE_PILE = 20;

    // Nombre d'offres chargees avant le tri en PHP. Le tri melange un score
    // metier, une distance et une date : aucune de ces trois cles ne se
    // calcule en SQL sans rendre la requete illisible, et 200 lignes
    // couvrent tres largement le volume reel (7 779 offres partenaires, une
    // seule offre Jeuncy publiee au 2026-09-22).
    private const CHARGEMENT_MAX = 200;

    // Boite englobante de secours quand le rayon depend de la ligne
    // (mobility_radius_km du candidat) : 100 km est le rayon maximum
    // acceptable par les Form Requests, donc aucune ligne eligible n'est
    // perdue. La haversine exacte filtre ensuite.
    private const RAYON_MAX_KM = 100;

    // Quotas quotidiens (MOBILE.md §5). Cote candidat le quota ne s'active
    // que si la pile est assez fournie pour que 20 gestes aient un sens.
    public const QUOTA_CANDIDAT = 20;

    public const QUOTA_EMPLOYEUR_PAR_OFFRE = 30;

    // Un « Passer » sur une offre partenaire la masque deux mois, pas pour
    // toujours : l'import LBA republie chaque nuit, et une offre ecartee en
    // juin peut redevenir pertinente en aout (le candidat a change de ville,
    // de diplome, d'avis).
    public const JOURS_MASQUAGE_PASS_PARTENAIRE = 60;

    public function __construct(
        private readonly CandidateProfileService $candidateProfileService,
        private readonly JobOfferService $jobOfferService,
        private readonly CompanyVerificationService $verificationService,
        private readonly BlockService $blockService,
        private readonly MatchScorer $scorer,
        private readonly CandidateCardPresenter $presenter,
    ) {}

    /**
     * Pile du candidat : offres Jeuncy puis offres partenaires.
     *
     * @return array{jeuncy: list<array<string, mixed>>, partner: LengthAwarePaginator, meta: array<string, mixed>}
     */
    public function offersForCandidate(User $user, int $page = 1): array
    {
        $profile = $this->candidateProfileService->requireProfile($user);

        [$lat, $lng, $source] = $this->positionDe($profile);
        $rayon = (int) ($profile->search_radius_km ?: 30);
        $departement = PostalCodes::department($profile->postal_code);

        [$offres, $portee] = $this->offresJeuncy($user, $profile, $lat, $lng, $rayon, $departement);
        [$partenaires, $porteePartenaire] = $this->offresPartenaires($profile, $lat, $lng, $rayon, $departement, $page);

        return [
            'jeuncy' => $offres,
            'partner' => $partenaires,
            'meta' => [
                'radius_km' => $rayon,
                'department' => $departement,
                'has_coordinates' => $lat !== null,
                'location_source' => $source,
                'scope' => $portee,
                // Les deux piles cascadent SEPAREMENT. Les lier ferait
                // dependre la portee des 7 779 offres partenaires de la
                // presence fortuite d'une offre Jeuncy : en production, ou
                // une seule offre Jeuncy est publiee, la moindre bascule de
                // celle-ci sur « france » envoyait au candidat vingt offres
                // partenaires tirees de toute la France, triees par date et
                // sans distance, au lieu de celles d'a cote.
                'partner_scope' => $porteePartenaire,
                'quota' => $this->quotaFor($profile),
            ],
        ];
    }

    /**
     * Pile de l'employeur pour une de ses offres.
     *
     * L'ORDRE DES GARDES EST DELIBERE : l'offre publiee de production (IDA)
     * n'a pas de code postal, et demander le perimetre avant la
     * geolocalisation lui repondrait « pas encore ouvert dans ce
     * departement » — faux, et sans rien a faire pour s'en sortir. « Indique
     * le code postal du poste » dit quoi faire.
     */
    public function candidatesForOffer(User $user, JobOffer $offer, int $page = 1): LengthAwarePaginator
    {
        $this->jobOfferService->requireOwnedOffer($user, $offer);
        $this->verificationService->requireVerified($user);

        // Avant meme la geolocalisation : un brouillon n'a pas de page
        // publique, donc un « Ca m'interesse » pose depuis ce deck enverrait
        // le candidat sur une offre introuvable. Et publier est le geste que
        // l'employeur a de toute facon a faire — le lui dire ici est plus
        // utile que de lui parler de code postal.
        if ($offer->status !== JobOfferStatus::PUBLISHED) {
            throw new ApiException(
                'JOB_OFFER_NOT_PUBLISHED',
                'Publie cette offre pour découvrir des candidats.',
                409,
            );
        }

        if ($offer->postal_code === null || ! $offer->hasCoordinates()) {
            throw new ApiException(
                'JOB_OFFER_NOT_LOCATED',
                'Indique le code postal du poste pour découvrir des candidats.',
                409,
            );
        }

        if (! MatchPerimeter::isOpen($offer->postal_code)) {
            throw new ApiException(
                'MATCH_NOT_OPEN_HERE',
                "Découvrir n'est pas encore ouvert dans ce département.",
                403,
            );
        }

        $profils = $this->candidatesQuery($user, $offer)
            ->with(['skills:id,name', 'software:id,name', 'languages', 'educations', 'experiences'])
            ->paginate(self::TAILLE_PILE, ['*'], 'page', $page);

        $competencesOffre = $offer->skills()->pluck('name')->all();

        return $profils->through(fn (CandidateProfile $profile) => array_merge(
            // coversOffer: true — la carte ne dit que « ta mobilite couvre
            // cette offre », jamais la distance ni la ville, et c'est vrai
            // par construction puisque la requete l'a filtre sur les DEUX
            // rayons.
            $this->presenter->present($profile, $offer, coversOffer: true),
            [
                'candidate_interested' => (bool) $profile->getAttribute('candidat_interesse'),
                'skills_in_common' => array_values(array_intersect(
                    $profile->skills->pluck('name')->all(),
                    $competencesOffre,
                )),
            ],
        ));
    }

    /**
     * Requete d'eligibilite du deck employeur, sans score ni tri par
     * distance. Publique : InterestService s'en sert pour verifier qu'un
     * LIKE employeur vise bien une carte que le deck lui aurait montree —
     * sans quoi un employeur pourrait liker un candidat hors de sa portee en
     * devinant un identifiant.
     *
     * @return Builder<CandidateProfile>
     */
    public function candidatesQuery(User $employer, JobOffer $offer): Builder
    {
        $ageMinimum = max(16, (int) ($offer->minimum_age ?: 16));
        $bloques = $this->blockService->blockedUserIdsFor($employer);
        $boite = Haversine::boundingBox((float) $offer->latitude, (float) $offer->longitude, self::RAYON_MAX_KM);
        $distance = Haversine::sql('candidate_profiles.latitude', 'candidate_profiles.longitude');
        $bindings = Haversine::bindings((float) $offer->latitude, (float) $offer->longitude);

        return CandidateProfile::query()
            ->select('candidate_profiles.*')
            ->where('is_visible_in_cvtheque', true)
            // Une date de naissance absente n'est pas une presomption
            // d'age : sans elle, le candidat n'entre pas dans le deck (et
            // le middleware match.age le lui a deja dit de son cote).
            ->whereNotNull('birth_date')
            ->whereDate('birth_date', '<=', now()->subYears($ageMinimum)->toDateString())
            // Position PROFILE seulement. device_* est le GPS du telephone :
            // il ne sert qu'a la pile du candidat, jamais a le rendre
            // visible d'un employeur (MOBILE.md §8).
            ->whereNotNull('candidate_profiles.latitude')
            ->whereNotNull('candidate_profiles.longitude')
            ->whereBetween('candidate_profiles.latitude', [$boite['minLat'], $boite['maxLat']])
            ->whereBetween('candidate_profiles.longitude', [$boite['minLng'], $boite['maxLng']])
            ->whereRaw("({$distance}) <= candidate_profiles.mobility_radius_km", $bindings)
            ->whereRaw("({$distance}) <= ?", [...$bindings, (int) ($offer->recruitment_radius_km ?: 30)])
            ->where(fn (Builder $q) => $this->filtreContrat($q, $offer))
            ->whereHas('user', fn ($q) => $q->where('is_suspended', false)->whereNull('deleted_account_at'))
            ->when($bloques !== [], fn (Builder $q) => $q->whereNotIn('candidate_profiles.user_id', $bloques))
            // Deja decide par cet employeur : la carte est sortie du deck.
            // Un candidat qui a PASSE l'offre en sort aussi : un LIKE sur lui
            // ne produirait ni match ni notification, et son absence est
            // indiscernable d'une inegibilite — rien n'est revele.
            ->whereDoesntHave('offerInterests', fn ($q) => $q
                ->where('job_offer_id', $offer->id)
                ->where(fn ($q2) => $q2
                    ->whereNotNull('employer_decision')
                    ->orWhere('candidate_decision', InterestDecision::PASS->value)))
            ->selectSub(
                OfferInterest::query()
                    ->selectRaw('1')
                    ->whereColumn('offer_interests.candidate_profile_id', 'candidate_profiles.id')
                    ->where('offer_interests.job_offer_id', $offer->id)
                    ->where('offer_interests.candidate_decision', InterestDecision::LIKE->value)
                    ->limit(1),
                'candidat_interesse',
            )
            // Ceux qui ont deja dit oui d'abord : c'est un match a un clic.
            // NULL passe en dernier en ordre descendant sur les deux pilotes.
            ->orderByDesc('candidat_interesse')
            ->orderByDesc('candidate_profiles.updated_at');
    }

    /**
     * Quota quotidien du candidat.
     *
     * Le quota ne s'active que si la pile compte au moins autant d'offres que
     * le quota lui-meme : plafonner a 20 quelqu'un qui n'a que 3 offres a
     * portee le bloquerait sans rien proteger. La production n'a qu'une seule
     * offre Jeuncy publiee au 2026-09-22 — le quota y est donc inactif, et
     * c'est voulu.
     *
     * @return array{limit: int, used: int, active: bool}
     */
    public function quotaFor(CandidateProfile $profile): array
    {
        $utilises = OfferInterest::query()
            ->where('candidate_profile_id', $profile->id)
            ->where('candidate_decision', InterestDecision::LIKE->value)
            ->where('candidate_decided_at', '>=', now()->subDay())
            ->count();

        [$lat, $lng] = $this->positionDe($profile);
        $rayon = (int) ($profile->search_radius_km ?: 30);
        $departement = PostalCodes::department($profile->postal_code);

        // Compte dans la portee reellement utilisee par la pile : la meme
        // cascade rayon -> departement -> France, sans quoi un candidat sans
        // coordonnees se verrait plafonne sur un comptage qui ne correspond
        // a aucun ecran.
        $aPortee = 0;
        foreach ($this->porteesSuccessives($lat, $lng, $rayon, $departement) as $portee) {
            $query = $this->pileJeuncyQuery($profile);
            $this->filtrePortee($query, $lat, $lng, $rayon, $departement, 'job_offers', $portee);
            $aPortee = $query->count();

            if ($aPortee > 0) {
                break;
            }
        }

        return [
            'limit' => self::QUOTA_CANDIDAT,
            'used' => $utilises,
            'active' => $aPortee >= self::QUOTA_CANDIDAT,
        ];
    }

    /**
     * Quota quotidien d'un employeur sur UNE offre. Par offre et non par
     * compte : une entreprise qui publie trois postes a trois piles a
     * parcourir, et un plafond global la bloquerait sur les deux autres.
     *
     * @return array{limit: int, used: int, active: bool}
     */
    public function employerQuotaFor(JobOffer $offer): array
    {
        $utilises = OfferInterest::query()
            ->where('job_offer_id', $offer->id)
            ->where('employer_decision', InterestDecision::LIKE->value)
            ->where('employer_decided_at', '>=', now()->subDay())
            ->count();

        return [
            'limit' => self::QUOTA_EMPLOYEUR_PAR_OFFRE,
            'used' => $utilises,
            'active' => true,
        ];
    }

    /**
     * Position retenue pour la pile : le GPS s'il a ete donne, sinon la
     * commune geocodee. Le GPS prime parce qu'un jeune qui cherche depuis
     * son lieu de stage ou de vacances veut voir ce qu'il y a AUTOUR DE LUI.
     *
     * @return array{0: ?float, 1: ?float, 2: ?string}
     */
    private function positionDe(CandidateProfile $profile): array
    {
        if ($profile->hasDeviceCoordinates()) {
            return [(float) $profile->device_latitude, (float) $profile->device_longitude, 'DEVICE'];
        }

        if ($profile->hasProfileCoordinates()) {
            return [(float) $profile->latitude, (float) $profile->longitude, 'PROFILE'];
        }

        return [null, null, null];
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: string}
     */
    private function offresJeuncy(
        User $user,
        CandidateProfile $profile,
        ?float $lat,
        ?float $lng,
        int $rayon,
        ?string $departement,
    ): array {
        $bloques = $this->blockService->blockedUserIdsFor($user);

        $base = fn () => $this->pileJeuncyQuery($profile)
            ->with(['company', 'cfaOrganization', 'skills:id,name'])
            ->when($bloques !== [], fn (Builder $q) => $q
                ->whereDoesntHave('company', fn ($c) => $c->whereIn('user_id', $bloques))
                ->whereDoesntHave('cfaOrganization', fn ($c) => $c->whereIn('user_id', $bloques)));

        // Rayon, sinon departement, sinon toute la France. Une pile vide est
        // le pire ecran possible pour un produit qui vit du geste : mieux
        // vaut une offre lointaine qu'aucune offre.
        foreach ($this->porteesSuccessives($lat, $lng, $rayon, $departement) as $portee) {
            $query = $base();
            $this->filtrePortee($query, $lat, $lng, $rayon, $departement, 'job_offers', $portee);

            if ($lat !== null) {
                $query->selectRaw(
                    'job_offers.*, '.Haversine::sql('job_offers.latitude', 'job_offers.longitude').' as distance_km',
                    Haversine::bindings($lat, $lng),
                );
            }

            $offres = $query->limit(self::CHARGEMENT_MAX)->get();

            if ($offres->isNotEmpty()) {
                return [$this->trierEtPresenter($offres, $profile), $portee];
            }
        }

        return [[], $lat !== null ? 'radius' : ($departement !== null ? 'department' : 'france')];
    }

    /**
     * @return list<string>
     */
    private function porteesSuccessives(?float $lat, ?float $lng, int $rayon, ?string $departement): array
    {
        if ($lat !== null) {
            return ['radius', 'france'];
        }

        return $departement !== null ? ['department', 'france'] : ['france'];
    }

    /**
     * Socle commun a la pile et au comptage du quota : offres publiees, non
     * decidees, auxquelles le candidat n'a pas deja postule.
     *
     * @return Builder<JobOffer>
     */
    private function pileJeuncyQuery(CandidateProfile $profile): Builder
    {
        return JobOffer::query()
            ->where('status', JobOfferStatus::PUBLISHED->value)
            ->whereDoesntHave('offerInterests', fn ($q) => $q
                ->where('candidate_profile_id', $profile->id)
                ->where(fn ($q2) => $q2->whereNotNull('candidate_decision')->orWhereNotNull('closed_at')))
            // Ceinture en plus du LIKE pose par ApplicationService : une offre
            // a laquelle il a postule depuis le site ne doit pas rester dans
            // sa pile, meme si la ligne d'interet manquait.
            ->whereDoesntHave('applications', fn ($q) => $q->where('candidate_profile_id', $profile->id))
            ->when(
                $profile->wantedContractTypes() !== [],
                fn (Builder $q) => $q->whereIn(
                    'contract_type',
                    array_map(fn ($type) => $type->value, $profile->wantedContractTypes()),
                ),
            );
    }

    /**
     * @param  Builder<JobOffer>|Builder<ExternalJobOffer>  $query
     */
    private function filtrePortee(
        Builder $query,
        ?float $lat,
        ?float $lng,
        int $rayon,
        ?string $departement,
        string $table,
        ?string $portee = null,
    ): Builder {
        $portee ??= $lat !== null ? 'radius' : ($departement !== null ? 'department' : 'france');

        if ($portee === 'radius' && $lat !== null && $lng !== null) {
            $boite = Haversine::boundingBox($lat, $lng, $rayon);

            return $query
                ->whereNotNull("{$table}.latitude")
                ->whereNotNull("{$table}.longitude")
                ->whereBetween("{$table}.latitude", [$boite['minLat'], $boite['maxLat']])
                ->whereBetween("{$table}.longitude", [$boite['minLng'], $boite['maxLng']])
                ->whereRaw(
                    '('.Haversine::sql("{$table}.latitude", "{$table}.longitude").') <= ?',
                    [...Haversine::bindings($lat, $lng), $rayon],
                );
        }

        if ($portee === 'department' && $departement !== null) {
            return $query->where("{$table}.postal_code", 'like', $this->prefixeCodePostal($departement).'%');
        }

        return $query;
    }

    // '66' -> '66', '2A'/'2B' -> '20' (les deux Corse partagent le meme
    // prefixe postal), '971' -> '971'.
    private function prefixeCodePostal(string $departement): string
    {
        return in_array($departement, ['2A', '2B'], true) ? '20' : $departement;
    }

    /**
     * @param  Builder<CandidateProfile>  $query
     */
    private function filtreContrat(Builder $query, JobOffer $offer): Builder
    {
        // Liste vide ou absente = aucune preference exprimee, donc eligible a
        // tout : c'est le cas de la quasi-totalite des 115 profils existants,
        // anterieurs a l'ecran « Ce que je cherche ».
        return $query
            ->whereNull('wanted_contract_types')
            ->orWhereJsonLength('wanted_contract_types', 0)
            ->orWhereJsonContains('wanted_contract_types', $offer->contract_type->value);
    }

    /**
     * @param  Collection<int, JobOffer>  $offres
     * @return list<array<string, mixed>>
     */
    private function trierEtPresenter($offres, CandidateProfile $profile): array
    {
        $interesses = OfferInterest::query()
            ->where('candidate_profile_id', $profile->id)
            ->whereIn('job_offer_id', $offres->pluck('id'))
            ->where('employer_decision', InterestDecision::LIKE->value)
            ->pluck('job_offer_id')
            ->all();

        $lignes = $offres->map(function (JobOffer $offre) use ($profile, $interesses) {
            $distance = $offre->getAttribute('distance_km');

            return [
                'offre' => $offre,
                'distance' => $distance === null ? null : round((float) $distance, 1),
                'employeur_interesse' => in_array($offre->id, $interesses, true),
                'score' => $this->scorer->score($profile, $offre),
            ];
        })->sortBy(fn (array $ligne) => [
            // Quatre cles, de la plus forte a la plus faible. Un employeur
            // qui a deja dit oui passe devant tout le reste : c'est un match
            // a un clic, et c'est ce que le candidat a de plus utile a voir.
            $ligne['employeur_interesse'] ? 0 : 1,
            -$ligne['score'],
            $ligne['distance'] ?? PHP_INT_MAX,
            -($ligne['offre']->published_at?->timestamp ?? 0),
        ])->take(self::TAILLE_PILE);

        return $lignes->map(function (array $ligne) {
            /** @var JobOffer $offre */
            $offre = $ligne['offre'];
            $donnees = $offre->toArray();
            unset($donnees['distance_km']);

            return array_merge($donnees, [
                'distance_km' => $ligne['distance'],
                'employer_interested' => $ligne['employeur_interesse'],
                // Toujours faux ici : la pile exclut les offres deja
                // postulees. La cle reste presente pour que le client n'ait
                // pas deux formes de carte a gerer.
                'already_applied' => false,
            ]);
        })->values()->all();
    }

    /**
     * @return array{0: LengthAwarePaginator, 1: string}
     */
    private function offresPartenaires(
        CandidateProfile $profile,
        ?float $lat,
        ?float $lng,
        int $rayon,
        ?string $departement,
        int $page,
    ): array {
        $ecartees = ExternalInterest::query()
            ->where('candidate_profile_id', $profile->id)
            ->where(fn ($q) => $q
                ->where('decision', ExternalInterestDecision::KEEP->value)
                ->orWhere(fn ($q2) => $q2
                    ->where('decision', ExternalInterestDecision::PASS->value)
                    ->where('decided_at', '>=', now()->subDays(self::JOURS_MASQUAGE_PASS_PARTENAIRE))))
            ->whereNotNull('external_job_offer_id')
            ->pluck('external_job_offer_id')
            ->all();

        $colonnes = array_map(fn (string $colonne) => "external_job_offers.{$colonne}", ExternalJobOffer::PUBLIC_COLUMNS);

        $base = fn () => ExternalJobOffer::query()
            ->select($colonnes)
            ->where('status', ExternalJobOfferStatus::ACTIVE->value)
            ->when($ecartees !== [], fn (Builder $q) => $q->whereNotIn('external_job_offers.id', $ecartees));

        // Meme cascade que la pile Jeuncy — rayon, sinon departement, sinon
        // toute la France — mais evaluee sur CETTE pile. La portee se decide
        // sur l'existence d'au moins une ligne, pas sur la page demandee :
        // sinon la page 3 d'une recherche locale basculerait sur la France
        // des qu'elle serait vide.
        $portees = $this->porteesSuccessives($lat, $lng, $rayon, $departement);
        $derniere = end($portees) ?: 'france';
        $portee = $derniere;
        $query = $base();

        foreach ($portees as $essayee) {
            $query = $base();
            $this->filtrePortee($query, $lat, $lng, $rayon, $departement, 'external_job_offers', $essayee);
            $portee = $essayee;

            // exists() ne consomme pas le builder : la meme requete sert
            // ensuite a paginer.
            if ($essayee === $derniere || $query->exists()) {
                break;
            }
        }

        if ($lat !== null && $portee === 'radius') {
            $query->selectRaw(
                Haversine::sql('external_job_offers.latitude', 'external_job_offers.longitude').' as distance_km',
                Haversine::bindings($lat, $lng),
            )->orderBy('distance_km');
        }

        $pagination = $query->orderByDesc('external_job_offers.published_at')
            ->orderByDesc('external_job_offers.id')
            ->paginate(self::TAILLE_PILE, ['*'], 'page', $page);

        return [$pagination->through(function (ExternalJobOffer $offre) {
            $distance = $offre->getAttribute('distance_km');
            $donnees = $offre->toArray();
            $donnees['distance_km'] = $distance === null ? null : round((float) $distance, 1);

            return $donnees;
        }), $portee];
    }
}
