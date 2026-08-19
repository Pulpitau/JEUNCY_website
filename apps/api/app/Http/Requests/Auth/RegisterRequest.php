<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
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
            // Le domaine d'anonymisation est refuse a l'inscription : il est
            // reserve aux comptes supprimes (voir
            // AccountService::deleteAccount). Sans cette garde, n'importe qui
            // pouvait s'y inscrire — .invalid n'est reserve que pour la
            // resolution DNS, ce qui n'empeche aucun formulaire de l'accepter.
            'email' => ['required', 'email', 'not_regex:/'.preg_quote(User::DELETED_EMAIL_DOMAIN, '/').'$/i'],
            'password' => ['required', 'string', 'min:8'],
            // ADMIN est volontairement exclu : ce role n'est jamais auto-attribuable.
            'role' => ['required', Rule::in(['CANDIDATE', 'COMPANY', 'CFA'])],
        ];
    }

    public function messages(): array
    {
        return [
            'email.email' => 'Adresse email invalide.',
            'email.not_regex' => "Cette adresse email n'est pas autorisée.",
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'role.in' => 'Choisis un type de compte valide.',
        ];
    }
}
