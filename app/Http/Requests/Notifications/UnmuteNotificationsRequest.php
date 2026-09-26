<?php

namespace App\Http\Requests\Notifications;

use App\Models\Conversations\NotificationMute;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UnmuteNotificationsRequest extends FormRequest
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
        ];
    }
}
