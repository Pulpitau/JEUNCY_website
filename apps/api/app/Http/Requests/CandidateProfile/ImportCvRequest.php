<?php

namespace App\Http\Requests\CandidateProfile;

use Illuminate\Foundation\Http\FormRequest;

class ImportCvRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cv' => ['required', 'file', 'mimes:pdf', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'cv.mimes' => 'Seuls les fichiers PDF sont acceptés pour l\'import.',
            'cv.max' => 'Le fichier ne doit pas dépasser 5 Mo.',
        ];
    }
}
