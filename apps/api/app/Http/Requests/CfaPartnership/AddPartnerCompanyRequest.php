<?php

namespace App\Http\Requests\CfaPartnership;

use Illuminate\Foundation\Http\FormRequest;

class AddPartnerCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
        ];
    }
}
