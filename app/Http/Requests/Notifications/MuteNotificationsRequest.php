<?php

namespace App\Http\Requests\Notifications;

use App\Exceptions\ApiException;
use App\Models\Conversations\Channel;
use App\Models\Conversations\NotificationMute;
use App\Models\Servers\Server;
use App\Models\Servers\ServerChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MuteNotificationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'target_type' => ['required', Rule::in(NotificationMute::TYPES)],
            'target_id' => ['required', 'integer', 'min:1'],
            // null — пока не включат; максимум — год.
            'minutes' => ['nullable', 'integer', 'min:1', 'max:525600'],
        ];
    }

    public function minutes(): ?int
    {
        return $this->filled('minutes') ? $this->integer('minutes') : null;
    }

    /** Что глушат: чат, сервер или канал сервера. Права на него проверяет контроллер. */
    public function target(): Channel|Server|ServerChannel
    {
        $id = $this->integer('target_id');

        return match ($this->validated('target_type')) {
            NotificationMute::TYPE_CHANNEL => Channel::query()->findOrFail($id),
            NotificationMute::TYPE_SERVER => Server::query()->findOrFail($id),
            NotificationMute::TYPE_SERVER_CHANNEL => ServerChannel::query()->findOrFail($id),
            default => throw new ApiException('Неизвестный тип уведомлений', 422),
        };
    }
}
