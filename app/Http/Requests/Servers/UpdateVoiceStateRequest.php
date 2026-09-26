<?php

namespace App\Http\Requests\Servers;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVoiceStateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'muted' => ['sometimes', 'boolean', 'required_without:deafened'],
            'deafened' => ['sometimes', 'boolean', 'required_without:muted'],
        ];
    }
}
