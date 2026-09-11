<?php

namespace App\Http\Requests\CandidateProfile;

use Illuminate\Foundation\Http\FormRequest;

class StoreCandidateProfileRequest extends FormRequest
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
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'headline' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9 .+-]*$/'],
            // Obligatoire depuis le 2026-09-11 : l'age est un critere de
            // selection pour les entreprises (le cout d'un alternant en
            // depend). 15 ans minimum, l'age requis pour un compte Jeuncy.
            'birth_date' => ['required', 'date', 'before_or_equal:'.now()->subYears(15)->toDateString(), 'after:'.now()->subYears(100)->toDateString()],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:10', 'regex:/^[0-9]*$/'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'hobbies' => ['nullable', 'string', 'max:500'],
            'driving_license' => ['nullable', 'string', 'max:100'],
            'video_url' => ['nullable', 'url', 'max:255'],
            'portfolio_url' => ['nullable', 'url', 'max:255'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],
        ];
    }
}
