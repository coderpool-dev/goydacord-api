<?php

namespace App\Http\Requests\Calls;

class CallHeartbeatRequest extends CallSessionRequest
{
    public function rules(): array
    {
        return [
            'session_id' => ['required', 'string'],
            'screen_sharing' => ['sometimes', 'boolean'],
        ];
    }

    public function sessionId(): string
    {
        return (string) parent::sessionId();
    }
}
