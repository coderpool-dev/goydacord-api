<?php

namespace App\Http\Requests\Servers;

use Illuminate\Foundation\Http\FormRequest;

class StoreServerChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'kind' => ['required', 'integer', 'in:1,2,3,4,5'],
            'topic' => ['nullable', 'string', 'max:1024'],
            'category_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
