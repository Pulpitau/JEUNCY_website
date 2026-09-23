<?php

namespace App\Http\Requests\Admin;

use App\Enums\VerificationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Decision humaine de verification (MOBILE.md §4.0).
 *
 * PENDING est refuse en entree : ce statut est celui de l'attente, pas une
 * decision. Y revenir a la main ne dirait rien de plus que ne rien faire, et
 * effacerait la trace de qui a tranche.
 *
 * La note est obligatoire, meme pour accorder. Ce statut ouvre l'acces a des
 * cartes de mineurs : six mois plus tard, une verification sans raison ecrite
 * est indistinguable d'une erreur de manipulation.
 */
class DecideVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::enum(VerificationStatus::class),
                Rule::notIn([VerificationStatus::PENDING->value]),
            ],
            'note' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.not_in' => 'Choisis « vérifiée » ou « refusée » : « en attente » n’est pas une décision.',
            'note.required' => 'Explique en une phrase pourquoi : cette note reste dans l’historique.',
        ];
    }
}
