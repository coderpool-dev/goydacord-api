<?php

namespace App\Http\Requests\Servers;

use App\Http\Requests\Concerns\TargetsServerChannel;
use Illuminate\Foundation\Http\FormRequest;

class StoreServerStickerRequest extends FormRequest
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
            'sticker' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_-]+\/[a-z0-9_-]+$/i'],
            'reply_to_id' => ['nullable', 'integer', 'exists:messages,id'],
        ];
    }
}
