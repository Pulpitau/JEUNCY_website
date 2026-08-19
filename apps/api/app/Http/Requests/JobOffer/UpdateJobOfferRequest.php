<?php

namespace App\Http\Requests\JobOffer;

use App\Enums\CompensationPeriod;
use App\Enums\ContractType;
use App\Enums\WorkMode;
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
        ];
    }
}
