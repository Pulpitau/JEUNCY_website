<?php

namespace App\Http\Requests\Application;

use Illuminate\Foundation\Http\FormRequest;

class StoreApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'job_offer_id' => ['required', 'integer', 'exists:job_offers,id'],
            'cover_letter' => ['nullable', 'string', 'max:3000'],
        ];
    }
}
