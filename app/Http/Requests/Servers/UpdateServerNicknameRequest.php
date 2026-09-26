<?php

namespace App\Http\Requests\Servers;

use Illuminate\Foundation\Http\FormRequest;

class UpdateServerNicknameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** nickname = null или пустая строка — сбросить к имени аккаунта. */
    public function rules(): array
    {
        return [
            'nickname' => ['present', 'nullable', 'string', 'max:32'],
        ];
    }
}
