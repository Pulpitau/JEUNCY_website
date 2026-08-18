<?php

namespace App\Http\Requests\Contact;

use Illuminate\Foundation\Http\FormRequest;

class SendContactMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'organization' => ['sometimes', 'nullable', 'string', 'max:160'],
            'subject' => ['required', 'string', 'max:160'],
            'message' => ['required', 'string', 'min:10', 'max:4000'],
            // Champ piege ("honeypot") : invisible pour un humain, rempli par
            // la plupart des robots a soumission automatique. Doit rester vide.
            // Complete la limitation de debit sans imposer de captcha au
            // visiteur legitime.
            'website' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'message.min' => 'Merci de détailler un peu ta demande (10 caractères minimum).',
            'website.prohibited' => 'Envoi refusé.',
        ];
    }
}
