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
            // Declaration « j'ai 15 ans ou plus ». Facultative ici : le client
            // mobile ne l'envoie pas encore, et l'exiger casserait son
            // inscription. Si elle est envoyee, elle doit etre vraie. La
            // verification REELLE est la date de naissance, imposee >= 15 ans
            // a la creation du profil (voir StoreCandidateProfileRequest).
            'age_confirmed' => ['sometimes', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.email' => 'Adresse email invalide.',
            'email.not_regex' => "Cette adresse email n'est pas autorisée.",
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'role.in' => 'Choisis un type de compte valide.',
            'age_confirmed.accepted' => 'Tu dois avoir 15 ans ou plus pour créer un compte.',
        ];
    }
}
