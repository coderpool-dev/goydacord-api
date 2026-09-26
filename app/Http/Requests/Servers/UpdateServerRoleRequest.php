<?php

namespace App\Http\Requests\Servers;

use Illuminate\Foundation\Http\FormRequest;

class UpdateServerRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'color' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'permissions' => ['sometimes', 'integer', 'min:0'],
            'hoist' => ['sometimes', 'boolean'],
            'mentionable' => ['sometimes', 'boolean'],
        ];
    }
}
