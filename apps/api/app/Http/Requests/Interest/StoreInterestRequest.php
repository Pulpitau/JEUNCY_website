<?php

namespace App\Http\Requests\Interest;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class StoreInterestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $estEmployeur = in_array($this->user()?->role, [UserRole::COMPANY, UserRole::CFA], true);

        return [
            'job_offer_id' => ['required', 'integer', 'exists:job_offers,id'],
            // Exige de l'employeur, IGNORE pour un candidat : le service
            // prend toujours le profil de l'appelant, jamais celui du corps
            // de la requete — sinon un candidat poserait un « Ca
            // m'interesse » au nom d'un autre.
            'candidate_profile_id' => [
                $estEmployeur ? 'required' : 'sometimes',
                'nullable',
                'integer',
                'exists:candidate_profiles,id',
            ],
        ];
    }
}
