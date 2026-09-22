<?php

namespace App\Http\Requests\JobOffer;

use App\Enums\CompensationPeriod;
use App\Enums\ContractType;
use App\Enums\OfferSector;
use App\Enums\WorkMode;
use App\Http\Requests\CandidateProfile\UpdateCandidatePreferencesRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateJobOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string', 'max:5000'],
            'contract_type' => ['sometimes', Rule::enum(ContractType::class)],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'work_mode' => ['sometimes', 'nullable', Rule::enum(WorkMode::class)],
            'compensation_amount' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:999999'],
            'compensation_period' => ['sometimes', 'nullable', Rule::enum(CompensationPeriod::class)],
            'experience_level' => ['sometimes', 'nullable', 'string', 'max:100'],
            'benefits' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'diploma_level' => ['sometimes', 'nullable', 'string', 'max:100'],
            'training_rhythm' => ['sometimes', 'nullable', 'string', 'max:255'],
            'skills' => ['sometimes', 'nullable', 'array'],
            'skills.*' => ['string', 'max:100'],
            // Voir StoreJobOfferRequest : memes champs du modele match.
            'postal_code' => ['sometimes', 'nullable', 'string', 'regex:/^\d{5}$/'],
            'recruitment_radius_km' => ['sometimes', 'integer', 'min:'.UpdateCandidatePreferencesRequest::MIN_RADIUS_KM, 'max:'.UpdateCandidatePreferencesRequest::MAX_RADIUS_KM],
            'sector' => ['sometimes', 'nullable', Rule::enum(OfferSector::class)],
            'schedule' => ['sometimes', 'nullable', 'string', 'max:255'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'minimum_age' => ['sometimes', 'integer', 'min:16', 'max:18'],
            'requires_driving_license' => ['sometimes', 'boolean'],
            'missions' => ['sometimes', 'nullable', 'array', 'max:8'],
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
