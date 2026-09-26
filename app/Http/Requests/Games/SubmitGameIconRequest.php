<?php

namespace App\Http\Requests\Games;

use Illuminate\Foundation\Http\FormRequest;

class SubmitGameIconRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'icon' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'source' => ['sometimes', 'nullable', 'string', 'max:32'],
            'hash' => ['sometimes', 'nullable', 'string', 'max:64'],
        ];
    }
}
