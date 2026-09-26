<?php

namespace App\Http\Requests\WebRTC;

use Illuminate\Foundation\Http\FormRequest;

class SendSignalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:offer,answer,candidate,bye,meta,status'],
            'sdp' => ['nullable', 'string'],
            'candidate' => ['nullable', 'array'],
            // meta: какой трек что показывает (msid => camera/screen).
            'payload' => ['nullable', 'array'],
            // status: микрофон выключен, звук выключен, человек говорит.
            'muted' => ['nullable', 'boolean'],
            'deafened' => ['nullable', 'boolean'],
            'speaking' => ['nullable', 'boolean'],
            // Кому переслать сигнал. Без получателя сигнал уходит всем в канале.
            'to_user_id' => ['nullable', 'integer'],
        ];
    }

    public function signal(): array
    {
        $validated = $this->validated();

        return [
            'type' => $validated['type'],
            'sdp' => $validated['sdp'] ?? null,
            'candidate' => $validated['candidate'] ?? null,
            'payload' => $validated['payload'] ?? null,
            'muted' => $validated['muted'] ?? null,
            'deafened' => $validated['deafened'] ?? null,
            'speaking' => $validated['speaking'] ?? null,
        ];
    }

    public function recipientId(): ?int
    {
        return $this->integer('to_user_id') ?: null;
    }
}
