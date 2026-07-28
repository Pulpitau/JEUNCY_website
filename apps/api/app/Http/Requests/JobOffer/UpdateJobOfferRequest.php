<?php

namespace App\Http\Requests\JobOffer;

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
            'contract_type' => ['sometimes', Rule::in(['ALTERNANCE', 'SAISONNIER', 'BENEVOLAT'])],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'compensation' => ['sometimes', 'nullable', 'string', 'max:255'],
            'experience_level' => ['sometimes', 'nullable', 'string', 'max:100'],
            'benefits' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'diploma_level' => ['sometimes', 'nullable', 'string', 'max:100'],
            'training_rhythm' => ['sometimes', 'nullable', 'string', 'max:255'],
            'skills' => ['sometimes', 'nullable', 'array'],
            'skills.*' => ['string', 'max:100'],
        ];
    }
}
