<?php

namespace App\Services;

use App\Enums\CvSource;
use App\Exceptions\ApiException;
use App\Models\CandidateProfile;
use App\Models\CvDownload;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// CVtheque : recherche de profils candidats reservee aux entreprises et CFA
// disposant d'un abonnement actif (decision produit du 2026-08-17 — c'est la
// contrepartie principale de l'abonnement, elle n'est plus achetable a l'unite).
//
// Deux garde-fous RGPD structurent tout ce service, et ils ne doivent pas etre
// contournes par une future evolution :
//
//  1. Seuls les profils dont le candidat n'a pas coupe la visibilite
//     (is_visible_in_cvtheque) sont interrogeables. Le filtre est applique en
//     premier, sur chaque requete, liste comme detail — un profil retire ne
//     doit pas rester accessible en tapant son identifiant directement.
//
//  2. Minimisation : la liste ne renvoie AUCUNE coordonnee directe (email,
//     telephone, adresse precise, date de naissance). Un recruteur qui parcourt
//     des resultats n'a pas besoin de pouvoir moissonner des contacts ; il voit
//     de quoi juger la pertinence d'un profil, et les coordonnees seulement
//     apres avoir ouvert la fiche (voir LIST_COLUMNS et DETAIL_RELATIONS).
class CvthequeService
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService,
        private readonly CvService $cvService,
        private readonly CandidateProfileService $profileService,
    ) {}

    // Colonnes renvoyees en liste. Volontairement restreint : ni phone, ni
    // address, ni birth_date, ni l'email du compte. La ville reste exposee, un
    // recruteur devant pouvoir filtrer geographiquement — c'est une donnee de
    // localisation grossiere, pas une adresse postale.
    private const LIST_COLUMNS = [
        'id', 'user_id', 'first_name', 'last_name', 'headline', 'city',
        'photo_url', 'bio', 'driving_license',
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

    public function search(User $user, array $filters): LengthAwarePaginator
    {
        $this->requireCvthequeAccess($user);

        $query = CandidateProfile::query()
            ->select(self::LIST_COLUMNS)
            ->where('is_visible_in_cvtheque', true)
            ->with(['skills:id,name', 'software:id,name', 'languages:id,candidate_profile_id,name,level'])
            ->latest('updated_at');

        if (! empty($filters['city'])) {
            $query->where('city', 'like', '%'.$filters['city'].'%');
        }

        if (! empty($filters['driving_license'])) {
            $query->whereNotNull('driving_license')->where('driving_license', '!=', '');
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

        // Recherche libre : titre pro, bio, et le contenu des experiences et
        // formations — c'est la que se trouve le metier reellement exerce.
        if (! empty($filters['q'])) {
            $term = '%'.$filters['q'].'%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('headline', 'like', $term)
                    ->orWhere('bio', 'like', $term)
                    ->orWhereHas('experiences', fn (Builder $sub) => $sub->where('title', 'like', $term)->orWhere('company', 'like', $term))
                    ->orWhereHas('educations', fn (Builder $sub) => $sub->where('degree', 'like', $term)->orWhere('field_of_study', 'like', $term)->orWhere('school', 'like', $term));
            });
        }

        return $query->paginate(12)->withQueryString();
    }

    // Fiche complete : coordonnees incluses, parce que c'est precisement ce que
    // le recruteur vient chercher une fois le profil juge pertinent. Le filtre
    // de visibilite est reapplique ici — sans lui, un profil retire de la
    // CVtheque resterait consultable par son identifiant.
    public function find(User $user, int $candidateProfileId): CandidateProfile
    {
        $this->requireCvthequeAccess($user);

        $profile = CandidateProfile::where('id', $candidateProfileId)
            ->where('is_visible_in_cvtheque', true)
            ->with([
                'user:id,email',
                'experiences',
                'educations',
                'languages',
                'skills:id,name',
                'software:id,name',
            ])
            ->first();

        if (! $profile) {
            throw new ApiException('CANDIDATE_PROFILE_NOT_FOUND', 'Profil introuvable.', 404);
        }

        // cv_file_url masquee : le recruteur ne doit jamais recevoir l'URL
        // publique du fichier. Avec elle il pourrait recuperer le CV en
        // contournant downloadCv(), donc sans passer par la garde d'abonnement
        // ni par le journal de telechargement — et la partager telle quelle.
        // Le telechargement passe exclusivement par cvtheque/{id}/cv.
        $profile->makeHidden(['cv_file_url']);

        // Le recruteur voit en revanche si le CV est celui que le candidat a
        // lui-meme depose, et de quand il date : un CV choisi par le candidat
        // n'a pas le meme statut qu'une fiche mise en page par Jeuncy.
        $profile->setAttribute('has_uploaded_cv', $profile->cv_file_url !== null);

        return $profile;
    }

    // Telechargement du CV d'un candidat par un recruteur abonne.
    //
    // Passe deliberement par find() : la garde d'abonnement ET le filtre de
    // visibilite CVtheque sont donc reappliques ici. Un candidat qui s'est
    // retire de la CVtheque ne doit pas voir son CV rester telechargeable par
    // quiconque aurait garde l'URL sous la main.
    //
    // Renvoie les octets du PDF plutot qu'une redirection vers le fichier
    // stocke : l'URL publique du CV ne doit jamais fuiter cote recruteur,
    // sinon elle circule ensuite hors de toute garde d'abonnement et hors du
    // journal de telechargement.
    public function downloadCv(User $user, int $candidateProfileId): array
    {
        $profile = $this->find($user, $candidateProfileId);
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

    // Ordre de priorite : ce que le candidat a choisi de presenter passe avant
    // ce que Jeuncy sait fabriquer.
    //
    //  1. Son CV depose (Canva, Word...) — c'est SON document.
    //  2. Son dernier CV genere sur Jeuncy et encore sur le disque.
    //  3. A defaut, un PDF fabrique a la volee depuis les donnees du profil.
    //
    // Le troisieme cas n'est pas un repli de secours mais le cas majoritaire
    // au demarrage : les profils deja en base n'ont pour la plupart jamais
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

    private function relativeStoragePath(string $url): string
    {
        $base = rtrim(Storage::disk('public')->url(''), '/').'/';

        return Str::startsWith($url, $base) ? substr($url, strlen($base)) : $url;
    }
}
