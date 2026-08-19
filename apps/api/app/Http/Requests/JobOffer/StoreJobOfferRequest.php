<?php

namespace App\Http\Requests\JobOffer;

use App\Enums\CompensationPeriod;
use App\Enums\ContractType;
use App\Enums\WorkMode;
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
        ];
    }
}
