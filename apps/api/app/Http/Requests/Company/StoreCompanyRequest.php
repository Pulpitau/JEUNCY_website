<?php

namespace App\Http\Requests\Company;

use App\Enums\WorkMode;
use App\Rules\ValidSiret;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            // Obligatoire depuis le modele match (MOBILE.md §4.0) : c'est la
            // seule preuve d'existence dont Jeuncy dispose avant de montrer
            // des candidats — dont des mineurs — a un employeur. La cle de
            // Luhn est verifiee ici pour qu'une faute de frappe revienne en
            // « SIRET invalide » a la saisie, et non en fiche refusee.
            'siret' => ['required', 'string', 'regex:/^\d{14}$/', 'unique:companies,siret', new ValidSiret],
            'description' => ['nullable', 'string', 'max:2000'],
            'website' => ['nullable', 'url', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'work_mode' => ['nullable', Rule::enum(WorkMode::class)],
        ];
    }
}
