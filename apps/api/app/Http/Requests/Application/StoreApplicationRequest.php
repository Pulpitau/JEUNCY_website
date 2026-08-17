<?php

namespace App\Http\Requests\Application;

use Illuminate\Foundation\Http\FormRequest;

class StoreApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'job_offer_id' => ['required', 'integer', 'exists:job_offers,id'],
            'cover_letter' => ['nullable', 'string', 'max:3000'],
            'contact_phone' => ['required', 'string', 'max:20', 'regex:/^[0-9 .+-]*$/'],
            // Un CV genere sur la plateforme OU un fichier importe, jamais aucun des
            // deux, l'un supplee l'absence de l'autre (required_without). Appartenance
            // au profil du candidat verifiee cote service (pas ici) : exists:generated_cvs,id
            // ne verifie que l'existence, pas le proprietaire.
            'generated_cv_id' => ['required_without:cv_file', 'nullable', 'integer', 'exists:generated_cvs,id'],
            'cv_file' => ['required_without:generated_cv_id', 'nullable', 'file', 'mimes:pdf', 'max:5120'],
        ];
    }
}
