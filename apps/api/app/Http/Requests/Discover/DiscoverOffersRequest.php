<?php

namespace App\Http\Requests\Discover;

use Illuminate\Foundation\Http\FormRequest;

class DiscoverOffersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Ne pagine que la pile PARTENAIRE : la pile Jeuncy est un lot
            // fini de 20 cartes, pas un defilement.
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
