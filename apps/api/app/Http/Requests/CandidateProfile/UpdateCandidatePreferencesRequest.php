<?php

namespace App\Http\Requests\CandidateProfile;

use App\Enums\ContractType;
use App\Enums\DrivingLicenseCategory;
use App\Enums\OfferSector;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * « Ce que je cherche » et « Mobilite » (MOBILE.md §3.1).
 */
class UpdateCandidatePreferencesRequest extends FormRequest
{
    // Bornes du rayon, communes au candidat et a l'employeur (MOBILE.md §6).
    public const MIN_RADIUS_KM = 5;

    public const MAX_RADIUS_KM = 100;

    public const MAX_SECTORS = 3;

    public const MAX_PITCH = 160;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'wanted_contract_types' => ['sometimes', 'array', 'max:5'],
            'wanted_contract_types.*' => [Rule::enum(ContractType::class)],
            // 1 a 3 secteurs : au-dela, « ce que je cherche » ne cherche plus rien.
            'wanted_sectors' => ['sometimes', 'array', 'max:'.self::MAX_SECTORS],
            'wanted_sectors.*' => [Rule::enum(OfferSector::class)],
            'search_radius_km' => ['sometimes', 'integer', 'min:'.self::MIN_RADIUS_KM, 'max:'.self::MAX_RADIUS_KM],
            'mobility_radius_km' => ['sometimes', 'integer', 'min:'.self::MIN_RADIUS_KM, 'max:'.self::MAX_RADIUS_KM],
            'has_driving_license' => ['sometimes', 'boolean'],
            'driving_license_categories' => ['sometimes', 'array', 'max:9'],
            'driving_license_categories.*' => [Rule::enum(DrivingLicenseCategory::class)],
            'has_vehicle' => ['sometimes', 'boolean'],
            'available_from' => ['sometimes', 'nullable', 'date', 'after_or_equal:'.now()->toDateString()],
            // Seul texte libre NOUVEAU montre a un employeur avant le
            // dossier : il est donc le seul endroit ou l'invariant « ni
            // telephone ni email avant candidature » peut se contourner en
            // une ligne. Le filtrage general (MOBILE.md §7) viendra ensuite ;
            // cette garde minimale, elle, doit exister des le premier jour.
            'pitch' => [
                'sometimes',
                'nullable',
                'string',
                'max:'.self::MAX_PITCH,
                'not_regex:/[\w.+-]+@[\w-]+\.[a-z]{2,}/i',
                'not_regex:/(?:\D*\d){10}/',
            ],
            'show_photo_to_employers' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'wanted_sectors.max' => 'Choisis au maximum '.self::MAX_SECTORS.' secteurs.',
            'search_radius_km.min' => 'Le rayon minimum est de '.self::MIN_RADIUS_KM.' km.',
            'search_radius_km.max' => 'Le rayon maximum est de '.self::MAX_RADIUS_KM.' km.',
            'mobility_radius_km.min' => 'Le rayon minimum est de '.self::MIN_RADIUS_KM.' km.',
            'mobility_radius_km.max' => 'Le rayon maximum est de '.self::MAX_RADIUS_KM.' km.',
            'pitch.not_regex' => 'Ta phrase ne doit pas contenir de coordonnées : elles sont transmises avec ton dossier.',
        ];
    }
}
