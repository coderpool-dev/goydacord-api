<?php

namespace App\Http\Requests\Servers;

use App\Http\Requests\Concerns\TargetsServerChannel;
use Illuminate\Foundation\Http\FormRequest;

class StoreServerMessageRequest extends FormRequest
{
    use TargetsServerChannel;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'server_channel_id' => ['required', 'integer'],
            'message' => ['required', 'string', 'max:1000'],
            'reply_to_id' => ['nullable', 'integer', 'exists:messages,id'],
        ];
    }
}
