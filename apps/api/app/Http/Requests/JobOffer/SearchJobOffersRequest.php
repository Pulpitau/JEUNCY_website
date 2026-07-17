<?php

namespace App\Http\Requests\JobOffer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchJobOffersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['sometimes', 'string', 'max:255'],
            'contract_type' => ['sometimes', Rule::in(['ALTERNANCE', 'SAISONNIER', 'BENEVOLAT'])],
            'city' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
