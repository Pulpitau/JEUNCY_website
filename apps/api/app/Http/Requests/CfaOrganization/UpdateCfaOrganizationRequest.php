<?php

namespace App\Http\Requests\CfaOrganization;

use App\Enums\WorkMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCfaOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'is_public' => ['sometimes', 'boolean'],
            'siret' => ['sometimes', 'nullable', 'string', 'size:14', Rule::unique('cfa_organizations', 'siret')->ignore($this->user()->cfaOrganization?->id)],
            'nda_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'qualiopi_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'diplomas_offered' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'diploma_level' => ['sometimes', 'nullable', 'string', 'max:100'],
            'training_mode' => ['sometimes', 'nullable', Rule::enum(WorkMode::class)],
            'website' => ['sometimes', 'nullable', 'url', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:10'],
        ];
    }
}
