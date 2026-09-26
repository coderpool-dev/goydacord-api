<?php

namespace App\Http\Requests\Servers;

use Illuminate\Foundation\Http\FormRequest;

class StoreServerRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'permissions' => ['nullable', 'integer', 'min:0'],
            'hoist' => ['nullable', 'boolean'],
            'mentionable' => ['nullable', 'boolean'],
        ];
    }
}
