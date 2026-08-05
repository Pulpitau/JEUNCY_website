<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class UploadCompanyLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'logo' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'logo.image' => 'Le fichier doit être une image.',
            'logo.mimes' => 'Formats acceptés : JPEG, PNG, WEBP.',
            'logo.max' => "L'image ne doit pas dépasser 2 Mo.",
        ];
    }
}
