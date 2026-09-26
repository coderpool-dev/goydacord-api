<?php

namespace App\Http\Requests\Calls;

use Illuminate\Foundation\Http\FormRequest;

/**
 * session_id — id вкладки или устройства в звонке: один пользователь может
 * сидеть в звонке с нескольких устройств.
 */
class CallSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'session_id' => ['nullable', 'string'],
        ];
    }

    public function sessionId(): ?string
    {
        $sessionId = $this->validated('session_id');

        return is_string($sessionId) && $sessionId !== '' ? substr($sessionId, 0, 64) : null;
    }
}
