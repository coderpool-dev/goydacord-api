<?php

namespace App\Http\Requests\Servers;

use Illuminate\Foundation\Http\FormRequest;

class UpdateServerChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'topic' => ['sometimes', 'nullable', 'string', 'max:1024'],
            'category_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'overwrites' => ['sometimes', 'array'],
            'overwrites.*.role_id' => ['required_with:overwrites', 'integer'],
            'overwrites.*.allow' => ['nullable', 'integer', 'min:0'],
            'overwrites.*.deny' => ['nullable', 'integer', 'min:0'],
            'member_overwrites' => ['sometimes', 'array'],
            'member_overwrites.*.member_id' => ['required_with:member_overwrites', 'integer'],
            'member_overwrites.*.allow' => ['nullable', 'integer', 'min:0'],
            'member_overwrites.*.deny' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
