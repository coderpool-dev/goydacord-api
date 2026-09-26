<?php

namespace App\Http\Requests\Servers;

use Illuminate\Foundation\Http\FormRequest;

class MoveVoiceMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** server_channel_id = null — отключить от голоса. */
    public function rules(): array
    {
        return [
            'server_channel_id' => ['present', 'nullable', 'integer', 'min:1'],
        ];
    }
}
