<?php

namespace App\Http\Requests\JobOffer;

use App\Enums\ContractType;
use App\Enums\WorkMode;
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
            'contract_type' => ['sometimes', Rule::enum(ContractType::class)],
            'city' => ['sometimes', 'string', 'max:255'],
            'work_mode' => ['sometimes', Rule::enum(WorkMode::class)],
        ];
    }
}
