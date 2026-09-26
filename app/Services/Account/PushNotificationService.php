<?php

namespace App\Services\Account;

use App\Models\Account\PushSubscription;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

/**
 * Web Push (VAPID): личные сообщения, упоминания на сервере, входящие звонки — даже когда вкладка
 * закрыта. Service Worker на фронте сам не показывает уведомление, если приложение открыто и в
 * фокусе (там всё видно и так). Отправка — после ответа клиенту: чужой push-сервис не должен
 * тормозить отправку сообщения. Протухшие подписки (404/410) удаляются.
 */
class PushNotificationService
{
    private const DESKTOP_APP_TTL_SECONDS = 60;

    public function isConfigured(): bool
    {
        return (bool) config('services.webpush.public_key') && (bool) config('services.webpush.private_key');
    }

    public function subscribe(int $userId, string $endpoint, string $p256dh, string $auth, ?string $userAgent): void
    {
        PushSubscription::query()->updateOrCreate(
            ['endpoint_hash' => hash('sha256', $endpoint)],
            [
                'user_id' => $userId,
                'endpoint' => $endpoint,
                'p256dh' => $p256dh,
                'auth' => $auth,
                'user_agent' => $userAgent ? mb_substr($userAgent, 0, 255) : null,
            ],
        );
    }

    public function unsubscribe(int $userId, string $endpoint): void
    {
        PushSubscription::query()
            ->where('user_id', $userId)
            ->where('endpoint_hash', hash('sha256', $endpoint))
            ->delete();
    }

    /**
     * @param  int[]  $userIds
     * @param  array{title: string, body: string, url: string, tag: string, icon?: string|null, kind?: string}  $payload
     */
    public function sendToUsers(array $userIds, array $payload): void
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        if ($userIds === [] || ! $this->isConfigured()) {
            return;
        }

        dispatch(fn () => $this->deliver($userIds, $payload))->afterResponse();
    }

    /** Приложение для Windows пингует /auth/online раз в 15 с даже из трея: без пинга минуту — закрыто. */
    public function markDesktopAppActive(int $userId): void
    {
        Cache::put(self::desktopAppKey($userId), true, self::DESKTOP_APP_TTL_SECONDS);
    }

    /**
     * Подписки, на которые шлём. Пока у пользователя запущено приложение для Windows, оно само показывает
     * уведомление, и клик по нему открывает приложение. Пуш в браузер на компьютере его дублировал бы,
     * а «Принять» в браузерном уведомлении открывало бы сайт вместо приложения. Телефонам шлём всегда.
     *
     * @param  int[]  $userIds
     * @return Collection<int, PushSubscription>
     */
    public function subscriptionsFor(array $userIds): Collection
    {
        $withDesktopApp = array_values(array_filter($userIds, fn (int $id) => Cache::has(self::desktopAppKey($id))));
        $devices = app(SessionDeviceParser::class);

        return PushSubscription::query()
            ->whereIn('user_id', $userIds)
            ->get()
            ->reject(fn (PushSubscription $sub) => in_array((int) $sub->user_id, $withDesktopApp, true)
                && $devices->platformKind($sub->user_agent) === 'web')
            ->values();
    }

    private static function desktopAppKey(int $userId): string
    {
        return "push:desktop-app:{$userId}";
    }

    /** @param int[] $userIds */
    private function deliver(array $userIds, array $payload): void
    {
        $subscriptions = $this->subscriptionsFor($userIds);
        if ($subscriptions->isEmpty()) {
            return;
        }

        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject' => config('services.webpush.subject'),
                    'publicKey' => config('services.webpush.public_key'),
                    'privateKey' => config('services.webpush.private_key'),
                ],
            ], ['TTL' => 3600, 'urgency' => ($payload['kind'] ?? '') === 'call' ? 'high' : 'normal'], 10);

            $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
            foreach ($subscriptions as $sub) {
                $webPush->queueNotification(
                    Subscription::create(['endpoint' => $sub->endpoint, 'keys' => ['p256dh' => $sub->p256dh, 'auth' => $sub->auth]]),
                    $json,
                );
            }

            foreach ($webPush->flush() as $report) {
                if ($report->isSubscriptionExpired()) {
                    PushSubscription::query()->where('endpoint_hash', hash('sha256', $report->getEndpoint()))->delete();
                } elseif (! $report->isSuccess()) {
                    Log::info('web_push_failed', ['reason' => mb_substr((string) $report->getReason(), 0, 200)]);
                }
            }
        } catch (Throwable $e) {
            Log::warning('web_push_error', ['error' => $e->getMessage()]);
        }
    }
}
