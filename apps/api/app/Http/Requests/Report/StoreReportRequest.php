<?php

namespace App\Http\Requests\Report;

use App\Enums\ReportContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Meme designation de cible que le blocage (voir StoreBlockRequest), plus
// offer_interest_id : un match se signale par sa propre ligne, l'appelant
// n'a pas a savoir qui est en face.
class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'context' => ['required', Rule::enum(ReportContext::class)],
            'reason' => ['required', 'string', 'max:40'],
            'details' => ['nullable', 'string', 'max:2000'],
            'candidate_profile_id' => [
                'required_without_all:job_offer_id,offer_interest_id',
                'nullable', 'integer', 'exists:candidate_profiles,id',
            ],
            'job_offer_id' => [
                'required_without_all:candidate_profile_id,offer_interest_id',
                'nullable', 'integer', 'exists:job_offers,id',
            ],
            'offer_interest_id' => [
                'required_without_all:candidate_profile_id,job_offer_id',
                'nullable', 'integer', 'exists:offer_interests,id',
            ],
        ];
    }
}
