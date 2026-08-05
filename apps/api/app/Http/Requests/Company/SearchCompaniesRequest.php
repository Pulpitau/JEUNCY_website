<?php

namespace App\Http\Requests\Company;

use App\Enums\ContractType;
use App\Enums\WorkMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchCompaniesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'city' => ['sometimes', 'string', 'max:255'],
            'contract_type' => ['sometimes', Rule::enum(ContractType::class)],
            'work_mode' => ['sometimes', Rule::enum(WorkMode::class)],
        ];
    }
}
