<?php

namespace App\Http\Controllers\API\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Push\DestroyPushSubscriptionRequest;
use App\Http\Requests\Push\StorePushSubscriptionRequest;
use App\Services\Account\PushNotificationService;
use Illuminate\Http\JsonResponse;

/** Подписка браузера на Web Push: публичный VAPID-ключ, сохранить/удалить подписку. */
class PushSubscriptionController extends Controller
{
    public function __construct(private readonly PushNotificationService $push) {}

    public function key(): JsonResponse
    {
        return $this->successResponse('Ключ для Web Push', [
            'public_key' => config('services.webpush.public_key'),
            'enabled' => $this->push->isConfigured(),
        ]);
    }

    public function store(StorePushSubscriptionRequest $request): JsonResponse
    {
        $this->push->subscribe(
            (int) $request->user()->id,
            $request->validated('endpoint'),
            $request->validated('keys.p256dh'),
            $request->validated('keys.auth'),
            $request->userAgent(),
        );

        return $this->successResponse('Уведомления включены');
    }

    public function destroy(DestroyPushSubscriptionRequest $request): JsonResponse
    {
        $this->push->unsubscribe((int) $request->user()->id, $request->validated('endpoint'));

        return $this->successResponse('Уведомления выключены');
    }
}
