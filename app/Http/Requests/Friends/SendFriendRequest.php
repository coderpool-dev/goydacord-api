<?php

namespace App\Http\Requests\Friends;

use App\Exceptions\ApiException;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class SendFriendRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'friend_login' => ['required', 'string', 'min:2', 'max:50'],
        ];
    }

    /** Кому отправляем заявку. Логин набирает пользователь, поэтому пробелы по краям отбрасываем. */
    public function friend(): User
    {
        return User::query()->where('login', trim($this->validated('friend_login')))->first()
            ?? throw new ApiException('Пользователь не найден', 404);
    }
}
