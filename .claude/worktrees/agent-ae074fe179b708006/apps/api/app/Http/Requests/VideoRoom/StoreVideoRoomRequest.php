<?php

namespace App\Http\Requests\VideoRoom;

use Illuminate\Foundation\Http\FormRequest;

class StoreVideoRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'participant_email' => ['nullable', 'email'],
            'scheduled_at' => ['nullable', 'date'],
        ];
    }
}
