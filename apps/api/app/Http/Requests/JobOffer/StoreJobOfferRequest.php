<?php

namespace App\Http\Requests\JobOffer;

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
            'contract_type' => ['required', Rule::in(['ALTERNANCE', 'SAISONNIER', 'BENEVOLAT'])],
            'location' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'compensation' => ['nullable', 'string', 'max:255'],
            'experience_level' => ['nullable', 'string', 'max:100'],
            'benefits' => ['nullable', 'string', 'max:2000'],
            'diploma_level' => ['nullable', 'string', 'max:100'],
            'training_rhythm' => ['nullable', 'string', 'max:255'],
            'skills' => ['nullable', 'array'],
            'skills.*' => ['string', 'max:100'],
        ];
    }
}
