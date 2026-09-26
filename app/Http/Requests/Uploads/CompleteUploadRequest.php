<?php

namespace App\Http\Requests\Uploads;

use App\Models\Uploads\UploadSession;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompleteUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var UploadSession $upload */
        $upload = $this->route('upload');

        return [
            'caption' => ['nullable', 'string', 'max:1000'],
            // Ответить можно только на обычное сообщение из того же канала.
            'reply_to_id' => [
                'nullable',
                'integer',
                Rule::exists('messages', 'id')
                    ->where('channels_id', $upload->channel_id)
                    ->whereNot('type', 'system'),
            ],
        ];
    }

    public function caption(): string
    {
        return trim((string) $this->validated('caption', ''));
    }

    public function replyToId(): ?int
    {
        $id = $this->validated('reply_to_id');

        return $id ? (int) $id : null;
    }
}
