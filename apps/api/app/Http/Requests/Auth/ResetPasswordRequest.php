<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

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
            // Meme regle qu'a l'inscription (RegisterRequest), audit de
            // securite du 2026-10-01 section C4.
            'newPassword' => ['required', 'string', Password::min(10)],
        ];
    }

    public function messages(): array
    {
        return [
            'newPassword.min' => 'Le mot de passe doit contenir au moins 10 caractères.',
        ];
    }
}
