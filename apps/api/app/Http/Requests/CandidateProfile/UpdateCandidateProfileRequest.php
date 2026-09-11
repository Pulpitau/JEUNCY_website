<?php

namespace App\Http\Requests\CandidateProfile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCandidateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function messages(): array
    {
        return [
            'birth_date.required' => 'Indique ta date de naissance : les entreprises en ont besoin pour te proposer un contrat.',
            'birth_date.before_or_equal' => 'Il faut avoir au moins 15 ans pour utiliser Jeuncy.',
            'birth_date.after' => 'Cette date de naissance ne semble pas correcte.',
        ];
    }

    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255'],
            'headline' => ['sometimes', 'nullable', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^[0-9 .+-]*$/'],
            // Peut etre omise (mise a jour partielle) mais plus jamais videe :
            // voir StoreCandidateProfileRequest.
            'birth_date' => array_merge(['sometimes'], ['required', 'date', 'before_or_equal:'.now()->subYears(15)->toDateString(), 'after:'.now()->subYears(100)->toDateString()]),
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:10', 'regex:/^[0-9]*$/'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'hobbies' => ['sometimes', 'nullable', 'string', 'max:500'],
            'driving_license' => ['sometimes', 'nullable', 'string', 'max:100'],
            'video_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'portfolio_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'linkedin_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            // Droit d'opposition a la CVtheque (RGPD art. 21). 'sometimes' et
            // non 'required' : un enregistrement du formulaire de profil qui
            // n'envoie pas ce champ ne doit surtout pas remettre le candidat
            // en visible a son insu.
            'is_visible_in_cvtheque' => ['sometimes', 'boolean'],
        ];
    }
}
