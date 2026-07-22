<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8'],
            // ADMIN est volontairement exclu : ce role n'est jamais auto-attribuable.
            'role' => ['required', Rule::in(['CANDIDATE', 'COMPANY', 'CFA'])],
        ];
    }

    public function messages(): array
    {
        return [
            'email.email' => 'Adresse email invalide.',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'role.in' => 'Choisis un type de compte valide.',
        ];
    }
}
