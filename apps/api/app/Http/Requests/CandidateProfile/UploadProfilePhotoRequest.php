<?php

namespace App\Http\Requests\CandidateProfile;

use Illuminate\Foundation\Http\FormRequest;

class UploadProfilePhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // 12 Mo et non 2 : une photo de telephone recente pese 4 a 10 Mo, et
            // la limite de 2 Mo bloquait des candidats (signale le 2026-10-01).
            // Le serveur accepte 128 Mo par envoi (upload_max_filesize) : cette
            // limite est un choix produit, jamais une contrainte technique.
            'photo' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:12288'],
        ];
    }

    public function messages(): array
    {
        return [
            'photo.image' => 'Le fichier doit être une image.',
            'photo.mimes' => 'Formats acceptés : JPEG, PNG, WEBP.',
            'photo.max' => "L'image ne doit pas dépasser 12 Mo.",
        ];
    }
}
