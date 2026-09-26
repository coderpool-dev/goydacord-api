<?php

namespace App\Http\Requests\Messages;

use App\Http\Requests\Concerns\TargetsChannel;
use Illuminate\Foundation\Http\FormRequest;

class StoreMessageRequest extends FormRequest
{
    use TargetsChannel;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'channels_id' => 'required|integer',
            'message' => 'required|string|max:1000',
            'reply_to_id' => 'nullable|integer|exists:messages,id',
        ];
    }
}
