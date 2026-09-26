<?php

namespace App\Http\Requests\Push;

use Illuminate\Foundation\Http\FormRequest;

/** Подписка браузера на Web Push — то, что отдаёт PushSubscription.toJSON(). */
class StorePushSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'endpoint' => ['required', 'url', 'max:2000', 'starts_with:https://'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
        ];
    }
}
