<?php

namespace App\Http\Requests\Servers;

use Illuminate\Foundation\Http\FormRequest;

class ReorderServerRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** role_ids — все роли сервера, кроме @everyone, сверху вниз. */
    public function rules(): array
    {
        return [
            'role_ids' => ['required', 'array', 'max:250'],
            'role_ids.*' => ['integer', 'distinct'],
        ];
    }
}
