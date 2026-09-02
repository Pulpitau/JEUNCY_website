<?php

namespace App\Http\Requests\CandidateProfile;

use Illuminate\Foundation\Http\FormRequest;

class UploadCvRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // PDF uniquement : c'est le seul format qui s'affiche a l'identique
            // chez le recruteur, et le seul que CvImportService sait relire pour
            // pre-remplir le profil. 5 Mo, meme plafond que le CV joint a une
            // candidature (StoreApplicationRequest).
            'cv_file' => ['required', 'file', 'mimes:pdf', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'cv_file.mimes' => 'Le CV doit être un fichier PDF.',
            'cv_file.max' => 'Le CV ne doit pas dépasser 5 Mo.',
        ];
    }
}
