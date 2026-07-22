<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'newPassword' => ['required', 'string', 'min:8'],
        ];
    }

    public function messages(): array
    {
        return [
            'newPassword.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
        ];
    }
}
