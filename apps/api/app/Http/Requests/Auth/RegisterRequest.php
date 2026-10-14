<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

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
            // min:8 laissait passer "password", "12345678" (audit de
            // securite du 2026-10-01, section C4). Pas de uncompromised()
            // (Have I Been Pwned) ici : ca appelle un service externe a
            // chaque inscription, ce qui aurait exige de truquer cet appel
            // dans toute la suite de tests — hors scope d'un correctif rapide.
            'password' => ['required', 'string', Password::min(10)],
            // ADMIN est volontairement exclu : ce role n'est jamais auto-attribuable.
            'role' => ['required', Rule::in(['CANDIDATE', 'COMPANY', 'CFA'])],
            // Declaration « j'ai 16 ans ou plus », exigee pour un compte
            // CANDIDATE (web et mobile l'envoient desormais tous les deux —
            // audit de securite du 2026-10-01, section B3 : auparavant
            // "sometimes" laissait un appel direct a l'API s'en dispenser).
            // Sans objet pour COMPANY/CFA — accepted_if (et non required_if +
            // accepted separes) est necessaire : "accepted" est une regle
            // implicite qui s'evalue meme sur un champ absent, elle casserait
            // l'inscription CFA/COMPANY si elle n'etait pas elle-meme
            // conditionnee au role. La verification REELLE reste la date de
            // naissance, imposee >= 16 ans a la creation du profil (voir
            // StoreCandidateProfileRequest) ; ceci n'est que la preuve datee
            // du consentement attendue par la CNIL et par Apple.
            'age_confirmed' => ['accepted_if:role,CANDIDATE'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.email' => 'Adresse email invalide.',
            'email.not_regex' => "Cette adresse email n'est pas autorisée.",
            'password.min' => 'Le mot de passe doit contenir au moins 10 caractères.',
            'role.in' => 'Choisis un type de compte valide.',
            'age_confirmed.accepted_if' => 'Tu dois avoir 16 ans ou plus pour créer un compte.',
        ];
    }
}
