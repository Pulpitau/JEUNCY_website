<?php

namespace App\Http\Requests\JobOffer;

use App\Enums\CompensationPeriod;
use App\Enums\ContractType;
use App\Enums\OfferSector;
use App\Enums\WorkMode;
use App\Http\Requests\CandidateProfile\UpdateCandidatePreferencesRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreJobOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'contract_type' => ['required', Rule::enum(ContractType::class)],
            'location' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'work_mode' => ['nullable', Rule::enum(WorkMode::class)],
            // Montant brut en euros entiers. Plafond volontairement large
            // (couvre un salaire annuel cadre) mais borne : une saisie a
            // sept chiffres est une erreur de frappe, pas une offre.
            'compensation_amount' => ['nullable', 'integer', 'min:1', 'max:999999'],
            'compensation_period' => ['nullable', Rule::enum(CompensationPeriod::class)],
            'experience_level' => ['nullable', 'string', 'max:100'],
            'benefits' => ['nullable', 'string', 'max:2000'],
            'diploma_level' => ['nullable', 'string', 'max:100'],
            'training_rhythm' => ['nullable', 'string', 'max:255'],
            'skills' => ['nullable', 'array'],
            'skills.*' => ['string', 'max:100'],
            // Champs du modele match (MOBILE.md §3.2 et §6). Le code postal
            // reste facultatif au brouillon : il n'est exige qu'a la
            // publication, avec repli sur celui de l'organisation.
            'postal_code' => ['nullable', 'string', 'regex:/^\d{5}$/'],
            // "sometimes" et NON "nullable" pour les trois colonnes qui ont
            // une valeur par defaut en base et n'acceptent pas NULL
            // (migration 2026_09_22_100001) : avec "nullable", un client qui
            // envoie explicitement null passe la validation, la valeur nulle
            // arrive au create() et la base refuse — une 500 la ou une 400
            // « champ invalide » est la bonne reponse.
            'recruitment_radius_km' => ['sometimes', 'integer', 'min:'.UpdateCandidatePreferencesRequest::MIN_RADIUS_KM, 'max:'.UpdateCandidatePreferencesRequest::MAX_RADIUS_KM],
            'sector' => ['nullable', Rule::enum(OfferSector::class)],
            'schedule' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            // Decouvrir est reserve aux 16 ans et plus ; une offre peut
            // exiger davantage (vente d'alcool, machines), jamais moins.
            'minimum_age' => ['sometimes', 'integer', 'min:16', 'max:18'],
            'requires_driving_license' => ['sometimes', 'boolean'],
            'missions' => ['nullable', 'array', 'max:8'],
            'missions.*' => ['string', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return [
            'postal_code.regex' => 'Indique un code postal à 5 chiffres.',
            'minimum_age.min' => "L'âge minimum ne peut pas être inférieur à 16 ans.",
        ];
    }
}
