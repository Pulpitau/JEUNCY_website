<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

// Correction manuelle du nom d'un candidat par un administrateur.
//
// Memes limites que le profil lui-meme (StoreCandidateProfileRequest) : rien
// n'interdit ici un nom inhabituel. L'outil sert a REPARER une extraction
// ratee, pas a imposer une forme de nom — la validation resterait fausse pour
// une partie des gens quelle que soit la regle choisie.
class UpdateCandidateNameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
        ];
    }
}
