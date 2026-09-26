<?php

namespace App\Http\Requests\Channels;

use Illuminate\Foundation\Http\FormRequest;

class ChannelRecipientsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recipients' => ['required', 'array'],
            'recipients.*' => ['integer', 'min:1'],
        ];
    }

    /** @return int[] */
    public function recipientIds(): array
    {
        return array_map('intval', $this->validated('recipients'));
    }
}
