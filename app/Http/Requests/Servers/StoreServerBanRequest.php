<?php

namespace App\Http\Requests\Servers;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreServerBanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function target(): User
    {
        return User::query()->findOrFail($this->integer('user_id'));
    }

    public function reason(): ?string
    {
        return $this->string('reason')->trim()->toString() ?: null;
    }
}
