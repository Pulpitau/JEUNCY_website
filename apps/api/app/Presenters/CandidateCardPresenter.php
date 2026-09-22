<?php

namespace App\Presenters;

use App\Models\CandidateProfile;
use App\Models\Education;
use App\Models\Experience;
use App\Models\JobOffer;
use App\Models\Language;
use App\Models\Skill;
use App\Models\Software;
use Illuminate\Support\Carbon;

/**
 * La regle d'exposition unique : ce qu'un employeur voit d'un candidat
 * AVANT que celui-ci ait postule (contrat lot 1 §4, MOBILE.md §4.3).
 *
 * Un seul presenteur sert le deck employeur de l'application, la liste et la
 * fiche de la CVtheque du site, et la carte d'un match. Ecrite une fois,
 * testee une fois : trois copies auraient diverge au premier champ ajoute,
 * et c'est toujours la copie oubliee qui fuit.
 *
 * LISTE BLANCHE, jamais liste noire : la methode construit un tableau champ
 * par champ. Une colonne ajoutee demain a candidate_profiles n'apparait donc
 * pas toute seule dans la carte — il faut l'ecrire ici, donc le decider.
 * Une liste noire aurait l'effet inverse : tout nouveau champ serait expose
 * par defaut, et l'oubli irait dans le mauvais sens.
 *
 * Ne sortent JAMAIS : last_name (l'initiale suffit), city, postal_code,
 * address, phone, email, birth_date, age exact (une tranche a la place),
 * latitude / longitude / device_*, cv_file_url, linkedin_url, video_url,
 * portfolio_url, bio, hobbies, user_id, updated_at. La candidature complete
 * (ApplicationService::listForOffer) reste inchangee : c'est le dossier, et
 * il part par un geste du candidat.
 */
class CandidateCardPresenter
{
    /**
     * @param  JobOffer|null  $offer  Offre en contexte : sert a marquer les
     *                                competences en commun et a situer la mobilite. Sans elle, aucune
     *                                comparaison n'est possible.
     * @param  bool|null  $coversOffer  Le rayon de mobilite du candidat
     *                                  couvre-t-il le lieu de l'offre ? Calcule par l'appelant (il a
     *                                  deja la distance en SQL). Null = la cle `mobility` est absente :
     *                                  repondre dans le vide laisserait deviner un rayon, donc
     *                                  approximer une zone de residence.
     * @return array<string, mixed>
     */
    public function present(CandidateProfile $profile, ?JobOffer $offer = null, ?bool $coversOffer = null): array
    {
        $carte = [
            'id' => $profile->id,
            'first_name' => $profile->first_name,
            'last_name_initial' => $this->initial($profile->last_name),
            'age_band' => $profile->age_band,
            'headline' => $profile->headline,
            'pitch' => $profile->pitch,
            'wanted_contract_types' => $this->values($profile->wanted_contract_types),
            'wanted_sectors' => $this->values($profile->wanted_sectors),
            'has_driving_license' => (bool) $profile->has_driving_license,
            'driving_license_categories' => $this->values($profile->driving_license_categories),
            'has_vehicle' => (bool) $profile->has_vehicle,
            'available_from' => $this->date($profile->available_from),
            'skills' => $this->skills($profile, $offer),
            'software' => $this->software($profile),
            'languages' => $this->languages($profile),
            'educations' => $this->educations($profile),
            'experiences' => $this->experiences($profile),
            // Une photo n'est montree que si le candidat l'a explicitement
            // accepte (MOBILE.md §3.1) : un portrait est la donnee la plus
            // identifiante d'un mineur, et le defaut est non.
            'photo_url' => $profile->show_photo_to_employers ? $profile->photo_url : null,
            // Le fait qu'un CV existe, jamais son URL : le document se
            // telecharge par la route gardee, apres candidature.
            'has_uploaded_cv' => $profile->cv_file_url !== null,
        ];

        if ($offer !== null && $coversOffer !== null) {
            $carte['mobility'] = ['covers_offer' => $coversOffer];
        }

        return $carte;
    }

    /**
     * Premiere lettre du nom, en majuscule. « Girard » -> « G ».
     *
     * mb_substr et non substr : un nom peut commencer par un caractere
     * accentue (« Élodie »), qu'un decoupage sur l'octet couperait en deux.
     */
    private function initial(?string $lastName): ?string
    {
        $nettoye = trim((string) $lastName);

        return $nettoye === '' ? null : mb_strtoupper(mb_substr($nettoye, 0, 1));
    }

    /**
     * Competences du candidat, celles en commun avec l'offre en tete.
     *
     * L'ordre est la valeur ajoutee : un recruteur lit les trois premieres
     * lignes d'une carte. Sans tri, il lirait d'abord ce qui ne l'interesse
     * pas.
     *
     * @return list<array{id: int, name: string, in_common: bool}>
     */
    private function skills(CandidateProfile $profile, ?JobOffer $offer): array
    {
        $attendues = $offer === null
            ? []
            : $offer->skills->pluck('id')->all();

        $competences = $profile->skills
            ->map(fn (Skill $skill) => [
                'id' => $skill->id,
                'name' => $skill->name,
                'in_common' => in_array($skill->id, $attendues, true),
            ])
            ->sortByDesc('in_common')
            ->values()
            ->all();

        return $competences;
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function software(CandidateProfile $profile): array
    {
        return $profile->software
            ->map(fn (Software $software) => ['id' => $software->id, 'name' => $software->name])
            ->values()
            ->all();
    }

    /**
     * @return list<array{name: string, level: ?string}>
     */
    private function languages(CandidateProfile $profile): array
    {
        return $profile->languages
            ->map(fn (Language $language) => ['name' => $language->name, 'level' => $language->level])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function educations(CandidateProfile $profile): array
    {
        return $profile->educations
            ->map(fn (Education $education) => [
                'degree' => $education->degree,
                'school' => $education->school,
                'field_of_study' => $education->field_of_study,
                'start_date' => $this->date($education->start_date),
                'end_date' => $this->date($education->end_date),
            ])
            ->values()
            ->all();
    }

    /**
     * Intitule, structure et dates seulement.
     *
     * Ni `location` ni `description` : la premiere donne la ville par un
     * autre chemin, la seconde est du texte libre ou un telephone finit
     * souvent (« joignable au 06... »). Les deux contournent l'invariant
     * « rien de localisant ni de nominatif avant le dossier ».
     *
     * @return list<array<string, mixed>>
     */
    private function experiences(CandidateProfile $profile): array
    {
        return $profile->experiences
            ->map(fn (Experience $experience) => [
                'title' => $experience->title,
                'company' => $experience->company,
                'start_date' => $this->date($experience->start_date),
                'end_date' => $this->date($experience->end_date),
            ])
            ->values()
            ->all();
    }

    /**
     * Date en 'Y-m-d', sans heure ni fuseau : une carte n'a pas besoin de
     * savoir a quelle seconde commence un contrat, et un horodatage complet
     * ferait fuiter le fuseau donc, approximativement, la region.
     */
    private function date(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $value instanceof Carbon
            ? $value->toDateString()
            : Carbon::parse((string) $value)->toDateString();
    }

    /**
     * Valeurs d'un champ json (types de contrat, secteurs, categories de
     * permis) en liste de chaines, meme si la colonne est nulle ou porte un
     * objet mal forme venu d'une ancienne ecriture.
     *
     * @return list<string>
     */
    private function values(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_map(
            fn ($item) => is_object($item) && property_exists($item, 'value') ? (string) $item->value : (string) $item,
            array_filter($value, fn ($item) => is_scalar($item) || (is_object($item) && property_exists($item, 'value'))),
        ));
    }
}
