<?php

namespace App\Http\Requests\Activity;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGameSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'in:start,end'],
            'game' => ['nullable', 'required_if:action,start', 'string', 'max:160'],
        ];
    }

    public function messages(): array
    {
        return [
            'game.required_if' => 'Укажите название игры',
        ];
    }

    public function isStart(): bool
    {
        return $this->validated('action') === 'start';
    }

    public function game(): ?string
    {
        $game = trim((string) $this->validated('game', ''));

        return $game !== '' ? $game : null;
    }
}
