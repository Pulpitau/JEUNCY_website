<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\CandidateProfile;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

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
    public function __construct(private readonly SubscriptionService $subscriptionService) {}

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

        return $profile;
    }
}
