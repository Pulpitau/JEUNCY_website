<?php

namespace App\Http\Requests\CfaPartnership;

use Illuminate\Foundation\Http\FormRequest;

class SearchVerifiedCompaniesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:2', 'max:255'],
        ];
    }
}
