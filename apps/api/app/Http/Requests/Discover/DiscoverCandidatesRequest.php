<?php

namespace App\Http\Requests\Discover;

use Illuminate\Foundation\Http\FormRequest;

class DiscoverCandidatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // exists ne prouve que l'existence : l'appartenance de l'offre a
            // l'appelant est verifiee par DiscoverService (requireOwnedOffer).
            'job_offer_id' => ['required', 'integer', 'exists:job_offers,id'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
