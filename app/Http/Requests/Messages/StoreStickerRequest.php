<?php

namespace App\Http\Requests\Messages;

use App\Http\Requests\Concerns\TargetsChannel;
use Illuminate\Foundation\Http\FormRequest;

class StoreStickerRequest extends FormRequest
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
            // Идентификатор стикера из встроенного каталога вида "pack/id"
            // (латиница/цифры/дефис/подчёркивание). Картинка резолвится на фронте.
            'sticker' => 'required|string|max:64|regex:/^[a-z0-9_-]+\/[a-z0-9_-]+$/i',
            'reply_to_id' => 'nullable|integer|exists:messages,id',
        ];
    }
}
