<?php

namespace App\Http\Requests\ExternalJobOffer;

use App\Enums\ContractType;
use App\Enums\WorkMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchExternalJobOffersRequest extends FormRequest
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
            'department' => ['sometimes', 'string', 'max:3'],
        ];
    }
}
