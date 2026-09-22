<?php

namespace App\Http\Requests\JobOffer;

use App\Enums\ContractType;
use App\Enums\OfferSector;
use App\Http\Requests\CandidateProfile\UpdateCandidatePreferencesRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Offre express (MOBILE.md §4.1) : le minimum pour qu'une offre existe,
 * soit publiee et entre dans Decouvrir. La description est generee et se
 * complete plus tard depuis « Mes offres ».
 *
 * Le code postal est REQUIS ici (et pas seulement a la publication) : une
 * offre express est publiee dans la foulee, et sans code postal elle
 * n'apparaitrait dans aucune pile.
 */
class StoreExpressJobOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'contract_type' => ['required', Rule::enum(ContractType::class)],
            'postal_code' => ['required', 'string', 'regex:/^\d{5}$/'],
            'city' => ['required', 'string', 'max:255'],
            'sector' => ['required', Rule::enum(OfferSector::class)],
            // "sometimes" et non "nullable" : la colonne a un defaut en base
            // (30 km) et refuse NULL. Voir StoreJobOfferRequest.
            'recruitment_radius_km' => [
                'sometimes',
                'integer',
                'min:'.UpdateCandidatePreferencesRequest::MIN_RADIUS_KM,
                'max:'.UpdateCandidatePreferencesRequest::MAX_RADIUS_KM,
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'postal_code.regex' => 'Indique un code postal à 5 chiffres.',
        ];
    }
}
