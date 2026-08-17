<?php

namespace App\Http\Requests\CfaOrganization;

use App\Enums\WorkMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchCfaOrganizationsRequest extends FormRequest
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
            'diploma_level' => ['sometimes', 'string', 'max:100'],
            'training_mode' => ['sometimes', Rule::enum(WorkMode::class)],
        ];
    }
}
