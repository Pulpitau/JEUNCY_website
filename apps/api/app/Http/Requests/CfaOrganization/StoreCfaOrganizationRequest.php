<?php

namespace App\Http\Requests\CfaOrganization;

use App\Enums\WorkMode;
use App\Rules\ValidSiret;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCfaOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function messages(): array
    {
        return [
            'siret.required' => 'Le numéro SIRET est obligatoire : il nous sert à vérifier ton organisme.',
            'siret.regex' => 'Le numéro SIRET compte 14 chiffres.',
        ];
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // Obligatoire a la CREATION seulement (voir
            // CfaOrganizationService::updateForUser pour la raison).
            'siret' => ['required', 'string', 'regex:/^\d{14}$/', 'unique:cfa_organizations,siret', new ValidSiret],
            'nda_number' => ['nullable', 'string', 'max:50'],
            'qualiopi_number' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:2000'],
            'diplomas_offered' => ['nullable', 'string', 'max:2000'],
            'diploma_level' => ['nullable', 'string', 'max:100'],
            'training_mode' => ['nullable', Rule::enum(WorkMode::class)],
            'website' => ['nullable', 'url', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:10'],
        ];
    }
}
