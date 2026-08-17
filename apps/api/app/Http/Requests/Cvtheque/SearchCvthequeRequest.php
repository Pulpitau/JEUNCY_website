<?php

namespace App\Http\Requests\Cvtheque;

use Illuminate\Foundation\Http\FormRequest;

class SearchCvthequeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['sometimes', 'string', 'max:255'],
            'city' => ['sometimes', 'string', 'max:255'],
            'language' => ['sometimes', 'string', 'max:100'],
            'driving_license' => ['sometimes', 'boolean'],
            // Bornees a 10 : chaque entree ajoute un whereHas, donc une
            // sous-requete. Sans plafond, une URL forgee avec 500 competences
            // suffirait a faire ramer la base.
            'skills' => ['sometimes', 'array', 'max:10'],
            'skills.*' => ['string', 'max:100'],
            'software' => ['sometimes', 'array', 'max:10'],
            'software.*' => ['string', 'max:100'],
        ];
    }
}
