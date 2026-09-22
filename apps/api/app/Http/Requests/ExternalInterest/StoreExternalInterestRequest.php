<?php

namespace App\Http\Requests\ExternalInterest;

use App\Enums\ExternalInterestDecision;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExternalInterestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'external_job_offer_id' => ['required', 'integer', 'exists:external_job_offers,id'],
            'decision' => ['required', Rule::enum(ExternalInterestDecision::class)],
        ];
    }
}
