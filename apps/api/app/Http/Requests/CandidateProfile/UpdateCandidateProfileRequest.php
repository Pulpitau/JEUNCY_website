<?php

namespace App\Http\Requests\CandidateProfile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCandidateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255'],
            'headline' => ['sometimes', 'nullable', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^[0-9 .+-]*$/'],
            'birth_date' => ['sometimes', 'nullable', 'date'],
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
