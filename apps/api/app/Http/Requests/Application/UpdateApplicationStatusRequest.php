<?php

namespace App\Http\Requests\Application;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateApplicationStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // SENT est le statut initial automatique, jamais reassigne manuellement.
            'status' => ['required', Rule::in(['SEEN', 'INTERVIEW', 'ACCEPTED', 'REJECTED'])],
        ];
    }
}
