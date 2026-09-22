<?php

namespace App\Http\Requests\Company;

use App\Enums\WorkMode;
use App\Rules\ValidSiret;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function messages(): array
    {
        return [
            'siret.required' => 'Le numéro SIRET est obligatoire : il nous sert à vérifier ton entreprise.',
            'siret.regex' => 'Le numéro SIRET compte 14 chiffres.',
            'siret.unique' => 'Une fiche entreprise existe déjà avec ce numéro SIRET.',
        ];
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'is_public' => ['sometimes', 'boolean'],
            // "sometimes" + "required" : le SIRET n'a pas a etre renvoye a
            // chaque modification, mais s'il est present il ne peut pas etre
            // vide — on ne retire pas une preuve d'existence deja donnee
            // (le service verifie la valeur APRES fusion, voir
            // CompanyService::updateForUser).
            'siret' => ['sometimes', 'required', 'string', 'regex:/^\d{14}$/', Rule::unique('companies', 'siret')->ignore($this->user()->company?->id), new ValidSiret],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'website' => ['sometimes', 'nullable', 'url', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:10'],
            'work_mode' => ['sometimes', 'nullable', Rule::enum(WorkMode::class)],
        ];
    }
}
