<?php

namespace App\Http\Requests\Block;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

/**
 * LA CIBLE SE DESIGNE SANS user_id. Ni la carte candidat (liste blanche du
 * presenteur) ni la fiche d'une organisation (user_id dans $hidden)
 * n'exposent l'identifiant du compte d'en face : un client qui devrait le
 * fournir serait oblige de l'inventer. L'employeur bloque par
 * candidate_profile_id, le candidat par job_offer_id, et le controleur
 * resout le compte.
 *
 * user_id reste accepte pour ADMIN / STAFF, qui travaillent sur des comptes
 * et non sur des cartes.
 */
class StoreBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $interne = in_array($this->user()?->role, [UserRole::ADMIN, UserRole::STAFF], true);

        return [
            'candidate_profile_id' => [
                'required_without_all:job_offer_id,user_id',
                'nullable', 'integer', 'exists:candidate_profiles,id',
            ],
            'job_offer_id' => [
                'required_without_all:candidate_profile_id,user_id',
                'nullable', 'integer', 'exists:job_offers,id',
            ],
            'user_id' => [
                'required_without_all:candidate_profile_id,job_offer_id',
                'nullable', 'integer', 'exists:users,id',
                function (string $attribute, mixed $value, \Closure $fail) use ($interne) {
                    if ($value !== null && ! $interne) {
                        $fail('Indique le candidat ou l\'offre concernée.');
                    }
                },
            ],
        ];
    }
}
