<?php

namespace App\Http\Requests\CfaPartnership;

use Illuminate\Foundation\Http\FormRequest;

class RecommendCandidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'candidate_profile_id' => ['required', 'integer', 'exists:candidate_profiles,id'],
            'job_offer_id' => ['required', 'integer', 'exists:job_offers,id'],
        ];
    }
}
