<?php

namespace App\Http\Requests\Interest;

use App\Enums\UserRole;
use App\Services\InterestService;
use Illuminate\Foundation\Http\FormRequest;

// « Passer » par lot : l'application accumule les gestes et les envoie
// groupes, pour qu'un metro sans reseau ne fasse pas reapparaitre vingt
// cartes deja ecartees.
class PassInterestsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $estEmployeur = in_array($this->user()?->role, [UserRole::COMPANY, UserRole::CFA], true);

        if ($estEmployeur) {
            return [
                'job_offer_id' => ['required', 'integer', 'exists:job_offers,id'],
                'candidate_profile_ids' => ['required', 'array', 'max:'.InterestService::LOT_MAX],
                'candidate_profile_ids.*' => ['integer', 'exists:candidate_profiles,id'],
            ];
        }

        return [
            'job_offer_ids' => ['required', 'array', 'max:'.InterestService::LOT_MAX],
            'job_offer_ids.*' => ['integer', 'exists:job_offers,id'],
        ];
    }
}
