<?php

namespace App\Http\Requests\CandidateProfile;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Position GPS du telephone (MOBILE.md §6). Elle n'est JAMAIS renvoyee
 * telle quelle : le serveur l'arrondit a ~1 km et ne rend que la source et
 * l'horodatage (voir CandidateProfileService::setDeviceLocation).
 */
class UpdateCandidateLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ];
    }

    public function messages(): array
    {
        return [
            'latitude.required' => 'Position incomplète.',
            'longitude.required' => 'Position incomplète.',
        ];
    }
}
