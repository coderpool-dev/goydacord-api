<?php

namespace App\Http\Requests\Servers;

use Illuminate\Foundation\Http\FormRequest;

class UpdateServerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'remove_icon' => ['sometimes'],
            'icon' => ['sometimes', 'image', 'mimes:jpeg,png,jpg,gif', 'max:6144'],
        ];
    }
}
