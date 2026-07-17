<?php

namespace App\Http\Requests\CandidateProfile;

use Illuminate\Foundation\Http\FormRequest;

class SyncSkillsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'names' => ['present', 'array', 'max:30'],
            'names.*' => ['required', 'string', 'max:50'],
        ];
    }
}
