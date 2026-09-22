<?php

namespace App\Services;

use App\Enums\CvSource;
use App\Enums\UserRole;
use App\Exceptions\ApiException;
use App\Models\Application;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\CvDownload;
use App\Models\User;
use App\Presenters\CandidateCardPresenter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

// CVtheque : recherche de profils candidats, ouverte aux entreprises et CFA
// (gratuite depuis le 2026-09-15, la garde d'abonnement subsiste pour les
// comptes qui n'ont aucun acces paye — voir SubscriptionService).
//
// Trois garde-fous structurent tout ce service, et ils ne doivent pas etre
// contournes par une future evolution :
//
//  1. Seuls les profils dont le candidat n'a pas coupe la visibilite
//     (is_visible_in_cvtheque) sont interrogeables. Le filtre est applique en
//     premier, sur chaque requete, liste comme detail — un profil retire ne
//     doit pas rester accessible en tapant son identifiant directement.
//
//  2. Regle d'exposition unique (MOBILE.md §4.3, contrat lot 1 §4) : ni la
//     liste ni la fiche ne renvoient le modele. Les deux passent par
//     CandidateCardPresenter, une liste blanche partagee avec le deck
//     employeur de l'app. Avant qu'un candidat ait postule, un recruteur ne
//     voit ni son nom complet, ni sa ville, ni ses coordonnees, ni son CV.
//     Corollaire : on ne CHERCHE pas non plus dans ce qu'on ne montre pas
//     (ville, bio, nom) — un filtre qui reduit les resultats revele la donnee
//     par inference, meme sans jamais l'afficher.
//
//  3. Le CV n'est servi que si le candidat a postule a une offre de cet
//     employeur (CV_NOT_SHARED sinon). Le document part quand le candidat
//     fait le geste, pas quand le recruteur le decide.
class CvthequeService
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService,
        private readonly CvService $cvService,
        private readonly CandidateProfileService $profileService,
        private readonly CompanyVerificationService $verificationService,
        private readonly BlockService $blockService,
        private readonly CandidateCardPresenter $presenter,
    ) {}

    // Colonnes chargees : exactement celles dont le presenteur a besoin.
    // Selectionner moins que la table entiere ne remplace pas la liste
    // blanche du presenteur, c'est une seconde barriere : une colonne jamais
    // lue ne peut pas fuir par un oubli de serialisation.
    //
    // birth_date est chargee UNIQUEMENT pour calculer la tranche d'age
    // (accesseur age_band) ; elle ne sort jamais du presenteur.
    private const PRESENTER_COLUMNS = [
        'id', 'user_id', 'first_name', 'last_name', 'birth_date', 'headline',
        'pitch', 'wanted_contract_types', 'wanted_sectors', 'has_driving_license',
        'driving_license_categories', 'has_vehicle', 'available_from',
        'photo_url', 'show_photo_to_employers', 'cv_file_url', 'updated_at',
    ];

    // Relations lues par le presenteur. Chargees ici plutot que laissees en
    // lazy load : 12 profils par page feraient sinon 60 requetes.
    private const PRESENTER_RELATIONS = [
        'skills:id,name',
        'software:id,name',
        'languages:id,candidate_profile_id,name,level',
        'educations',
        'experiences',
    ];

    // hasPaidAccess et non hasActiveSubscription : un compte ADMIN consulte la
    // CVtheque sans abonnement, pour voir ce que voit un client qui paie.
    public function hasAccess(User $user): bool
    {
        return $this->subscriptionService->hasPaidAccess($user);
    }

    public function requireCvthequeAccess(User $user): void
    {
        if (! $this->hasAccess($user)) {
            throw new ApiException(
                'CVTHEQUE_SUBSCRIPTION_REQUIRED',
                "L'accès à la CVthèque est réservé aux abonnés.",
                402,
            );
        }
    }

    /**
     * Les deux gardes d'entree, TOUJOURS dans cet ordre.
     *
     * L'abonnement d'abord : un compte sans acces doit recevoir son 402, pas
     * un 403 de verification qui lui ferait chercher le probleme du mauvais
     * cote (et qui, pour un compte sans fiche entreprise du tout, serait de
     * surcroit trompeur).
     *
     * requireVerified laisse passer ADMIN et STAFF : ils n'ont pas
     * d'organisation a verifier. L'appeler sans condition evite de dupliquer
     * cette liste de roles ici.
     */
    private function requireAccess(User $user): void
    {
        $this->requireCvthequeAccess($user);
        $this->verificationService->requireVerified($user);
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function search(User $user, array $filters): LengthAwarePaginator
    {
        $this->requireAccess($user);

        $query = CandidateProfile::query()
            ->select(self::PRESENTER_COLUMNS)
            ->where('is_visible_in_cvtheque', true)
            ->with(self::PRESENTER_RELATIONS)
            ->latest('updated_at');

        // Blocage mutuel : ni celui que l'employeur a bloque, ni celui qui a
        // bloque l'employeur. Exclusion silencieuse — un profil absent est
        // indiscernable d'un profil qui n'existe pas.
        $blockedUserIds = $this->blockService->blockedUserIdsFor($user);
        if ($blockedUserIds !== []) {
            $query->whereNotIn('user_id', $blockedUserIds);
        }

        if (! empty($filters['has_driving_license'])) {
            $query->where('has_driving_license', true);
        }

        // Filtre par age. Le cout d'un alternant depend de sa tranche d'age,
        // c'est un critere de selection a part entiere. Calcule sur la date
        // de naissance, sans jamais l'exposer. Un profil sans date de
        // naissance est exclu des qu'un filtre d'age est pose : on ne peut
        // pas affirmer qu'il correspond.
        if (! empty($filters['age_min'])) {
            $query->whereNotNull('birth_date')
                ->whereDate('birth_date', '<=', now()->subYears((int) $filters['age_min'])->toDateString());
        }
        if (! empty($filters['age_max'])) {
            // « 25 ans au plus » = ne pas avoir encore 26 ans.
            $query->whereNotNull('birth_date')
                ->whereDate('birth_date', '>', now()->subYears((int) $filters['age_max'] + 1)->toDateString());
        }

        // Compétences et logiciels : un profil doit posséder TOUTES celles
        // demandées (un whereIn unique donnerait un OU, beaucoup trop large
        // pour un recruteur qui coche trois compétences précises).
        foreach ((array) ($filters['skills'] ?? []) as $skill) {
            $query->whereHas('skills', fn (Builder $q) => $q->where('name', $skill));
        }
        foreach ((array) ($filters['software'] ?? []) as $software) {
            $query->whereHas('software', fn (Builder $q) => $q->where('name', $software));
        }

        if (! empty($filters['language'])) {
            $query->whereHas('languages', fn (Builder $q) => $q->where('name', 'like', '%'.$filters['language'].'%'));
        }

        // Recherche libre : titre pro, experiences, formations — c'est la que
        // se trouve le metier reellement exerce.
        //
        // Ni bio ni ville ici, et le nom seulement pour l'equipe Jeuncy : ces
        // trois champs ne sont plus montres, et chercher dedans les revelerait
        // par inference (taper « Perpignan » et compter les resultats vaut
        // affichage de la ville). Le besoin du collegue STAFF — retrouver
        // quelqu'un qu'il vient d'avoir au telephone — reste servi, lui,
        // parce que l'equipe est deja responsable de traitement de ces
        // donnees.
        if (! empty($filters['q'])) {
            $term = '%'.$filters['q'].'%';
            $internal = $this->isInternal($user);
            $query->where(function (Builder $q) use ($term, $internal) {
                $q->where('first_name', 'like', $term)
                    ->orWhere('headline', 'like', $term)
                    ->orWhereHas('experiences', fn (Builder $sub) => $sub->where('title', 'like', $term)->orWhere('company', 'like', $term))
                    ->orWhereHas('educations', fn (Builder $sub) => $sub->where('degree', 'like', $term)->orWhere('field_of_study', 'like', $term)->orWhere('school', 'like', $term));

                if ($internal) {
                    $q->orWhere('last_name', 'like', $term)
                        ->orWhereRaw("CONCAT(first_name, ' ', last_name) like ?", [$term]);
                }
            });
        }

        return $query->paginate(12)
            ->withQueryString()
            ->through(fn (CandidateProfile $profile) => $this->presenter->present($profile));
    }

    /**
     * Fiche d'un candidat. Meme liste blanche que la liste : ouvrir une fiche
     * n'ouvre plus de coordonnees, elle donne le detail (competences,
     * formations, experiences) et dit si le CV est partageable.
     *
     * Renvoie un tableau et non le modele : c'est le presenteur qui decide
     * de ce qui sort, il n'y a plus de modele a serialiser.
     *
     * @return array<string, mixed>
     */
    public function find(User $user, int $candidateProfileId): array
    {
        $this->requireAccess($user);

        $profile = $this->visibleProfileOrFail($user, $candidateProfileId);

        return $this->presenter->present($profile) + [
            'cv_available' => $this->isCvSharedWith($user, $profile),
        ];
    }

    // Telechargement du CV d'un candidat par un recruteur.
    //
    // Renvoie les octets du PDF plutot qu'une redirection vers le fichier
    // stocke : l'URL publique du CV ne doit jamais fuiter cote recruteur,
    // sinon elle circule ensuite hors de toute garde et hors du journal de
    // telechargement.
    //
    // Ne passe plus par find() : celui-ci rend desormais un tableau, et le
    // rendu du PDF a besoin du modele. Les memes gardes sont reappliquees ici
    // — abonnement, verification, visibilite, blocage — plus le partage du CV.
    public function downloadCv(User $user, int $candidateProfileId): array
    {
        $this->requireAccess($user);

        $profile = $this->visibleProfileOrFail($user, $candidateProfileId);

        if (! $this->isCvSharedWith($user, $profile)) {
            throw new ApiException(
                'CV_NOT_SHARED',
                "Le CV est partagé quand le candidat postule à l'une de tes offres.",
                403,
            );
        }

        [$source, $contents] = $this->resolveCvFor($profile);

        CvDownload::create([
            'candidate_profile_id' => $profile->id,
            'user_id' => $user->id,
            'source' => $source,
        ]);

        return [
            'contents' => $contents,
            'filename' => $this->downloadFilename($profile, $source),
        ];
    }

    /**
     * Le profil, s'il est consultable par cet utilisateur. Une seule requete
     * pour la fiche et pour le telechargement : la garde de visibilite et
     * celle de blocage ne peuvent pas diverger entre les deux.
     *
     * Un profil bloque rend le meme CANDIDATE_PROFILE_NOT_FOUND qu'un profil
     * retire : confirmer l'existence d'une ligne bloquee serait deja une
     * information.
     */
    private function visibleProfileOrFail(User $user, int $candidateProfileId): CandidateProfile
    {
        $query = CandidateProfile::query()
            ->where('id', $candidateProfileId)
            ->where('is_visible_in_cvtheque', true)
            ->with(self::PRESENTER_RELATIONS);

        $blockedUserIds = $this->blockService->blockedUserIdsFor($user);
        if ($blockedUserIds !== []) {
            $query->whereNotIn('user_id', $blockedUserIds);
        }

        $profile = $query->first();

        if (! $profile) {
            throw new ApiException('CANDIDATE_PROFILE_NOT_FOUND', 'Profil introuvable.', 404);
        }

        return $profile;
    }

    /**
     * Le CV est-il partage avec cet utilisateur ?
     *
     * ADMIN et STAFF : oui, acces interne deja assume par la route
     * (routes/api/cvtheque.php). Un employeur : seulement si le candidat a
     * postule a l'une de SES offres — c'est le dossier qui ouvre le CV, et le
     * dossier suppose un geste du candidat.
     */
    private function isCvSharedWith(User $user, CandidateProfile $profile): bool
    {
        if ($this->isInternal($user)) {
            return true;
        }

        $organization = $this->verificationService->organizationFor($user);
        if (! $organization) {
            return false;
        }

        $column = $organization instanceof Company ? 'company_id' : 'cfa_organization_id';

        return Application::query()
            ->where('candidate_profile_id', $profile->id)
            ->whereHas('jobOffer', fn (Builder $q) => $q->where($column, $organization->id))
            ->exists();
    }

    private function isInternal(User $user): bool
    {
        return in_array($user->role, [UserRole::ADMIN, UserRole::STAFF], true);
    }

    // Ordre de priorite : ce que le candidat a choisi de presenter passe avant
    // ce que Jeuncy sait fabriquer.
    //
    //  1. Son CV depose (Canva, Word...) — c'est SON document.
    //  2. A defaut, un PDF fabrique a la volee depuis les donnees du profil.
    //
    // Le second n'est pas un repli de secours mais le cas majoritaire au
    // demarrage : les profils deja en base n'ont pour la plupart jamais
    // clique sur "Generer mon CV". Sans lui, la CVtheque serait vide de CV
    // pour presque tout le monde.
    private function resolveCvFor(CandidateProfile $profile): array
    {
        $uploadedPath = $this->profileService->uploadedCvAbsolutePath($profile);
        if ($uploadedPath && is_file($uploadedPath)) {
            $contents = file_get_contents($uploadedPath);
            if ($contents !== false) {
                return [CvSource::UPLOADED, $contents];
            }
        }

        // Le CV genere et stocke n'est deliberement PAS servi ici. Deux
        // raisons, la seconde ayant coute cher :
        //
        //  1. Produit : un fichier stocke est une photographie du profil au
        //     jour de sa generation. Le recruteur doit voir le profil TEL
        //     QU'IL EST, pas tel qu'il etait il y a trois mois.
        //  2. Exploitation : tant qu'un vieux fichier reste sur le disque il
        //     est servi indefiniment. Une correction du gabarit ne se voit
        //     alors jamais cote recruteur, et on croit la correction
        //     inefficace alors qu'elle n'est simplement jamais executee.
        //
        // Le rendu a la demande coute environ une seconde, largement
        // acceptable pour un telechargement declenche par un humain.
        return [CvSource::ON_THE_FLY, $this->cvService->renderPdfFor($profile)];
    }

    // Nom vu par le recruteur au telechargement. Pour un CV depose on garde le
    // nom d'origine du candidat ; sinon on en compose un lisible plutot que de
    // laisser un UUID, un recruteur classant des dizaines de CV devant pouvoir
    // les retrouver.
    private function downloadFilename(CandidateProfile $profile, CvSource $source): string
    {
        if ($source === CvSource::UPLOADED && $profile->cv_original_filename) {
            return $profile->cv_original_filename;
        }

        $name = trim(($profile->first_name ?? '').' '.($profile->last_name ?? ''));

        return 'CV-'.(Str::slug($name) ?: 'candidat-'.$profile->id).'.pdf';
    }
}
