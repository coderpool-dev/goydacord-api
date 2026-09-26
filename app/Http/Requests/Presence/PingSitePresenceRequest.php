<?php

namespace App\Http\Requests\Presence;

use Illuminate\Foundation\Http\FormRequest;

class PingSitePresenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'session_key' => ['required', 'string', 'min:8', 'max:64'],
            'path' => ['nullable', 'string', 'max:512'],
            'referrer' => ['nullable', 'string', 'max:1024'],
        ];
    }
}
