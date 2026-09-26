<?php

namespace App\Http\Requests\Games;

use Illuminate\Foundation\Http\FormRequest;

class LookupGameIconRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Укажите название игры',
        ];
    }
}
