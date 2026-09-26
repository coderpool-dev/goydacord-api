<?php

namespace App\Services\Conversations;

use App\Models\Conversations\Call;
use App\Models\Servers\ServerChannel;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Голосовые каналы серверов через LiveKit (SFU): медиа идёт через сервер, а не P2P-сеткой
 * между всеми участниками, поэтому канал держит десятки людей и демок. Здесь — токен на вход
 * в комнату и серверная модерация через RoomService API (Twirp/JSON): заглушённый модератором
 * физически не может публиковать микрофон, отключённого выкидывает из комнаты.
 *
 * Пока LIVEKIT_* не заданы, isEnabled() = false и клиент работает по-старому, через P2P.
 */
class LiveKitService
{
    private const TOKEN_TTL_SECONDS = 6 * 3600;

    private const API_TIMEOUT_SECONDS = 3;

    private const ALL_SOURCES = ['camera', 'microphone', 'screen_share', 'screen_share_audio'];

    public function isEnabled(): bool
    {
        return (bool) config('services.livekit.url')
            && (bool) config('services.livekit.api_key')
            && (bool) config('services.livekit.api_secret');
    }

    public function roomName(int $serverChannelId): string
    {
        return "server-channel-{$serverChannelId}";
    }

    /**
     * Данные для клиента: куда подключаться и токен. null — LiveKit не настроен.
     *
     * @return array{url: string, token: string, room: string}|null
     */
    public function connectionFor(User $user, ServerChannel $channel, string $displayName, bool $canSpeak): ?array
    {
        if (! $this->isEnabled()) {
            return null;
        }

        return $this->connection($this->roomName((int) $channel->id), $user, $displayName, $this->sources($canSpeak));
    }

    /**
     * Звонок в ЛС/беседе — своя комната на каждый звонок (call_id), чтобы новый звонок в том же
     * чате не встретил «хвосты» старого.
     *
     * @return array{url: string, token: string, room: string}|null
     */
    public function connectionForCall(User $user, Call $call): ?array
    {
        if (! $this->isEnabled()) {
            return null;
        }

        return $this->connection("call-{$call->call_id}", $user, (string) ($user->name ?? $user->login), self::ALL_SOURCES);
    }

    /**
     * @param  list<string>  $sources
     * @return array{url: string, token: string, room: string}
     */
    private function connection(string $room, User $user, string $displayName, array $sources): array
    {
        $now = time();

        $token = $this->sign([
            'iss' => config('services.livekit.api_key'),
            'sub' => (string) $user->id,
            'name' => $displayName,
            'nbf' => $now - 10,
            'exp' => $now + self::TOKEN_TTL_SECONDS,
            'video' => [
                'room' => $room,
                'roomJoin' => true,
                'canSubscribe' => true,
                'canPublish' => true,
                'canPublishData' => true,
                // Статус «без звука» (deafened) клиент кладёт в свои атрибуты.
                'canUpdateOwnMetadata' => true,
                'canPublishSources' => $sources,
            ],
        ]);

        return ['url' => (string) config('services.livekit.url'), 'token' => $token, 'room' => $room];
    }

    /** Заглушили/разглушили модератором (или сменилось право «Говорить») — сервер LiveKit применяет сам. */
    public function setCanSpeak(int $serverChannelId, int $userId, bool $canSpeak): void
    {
        $this->sendRoomRequest('UpdateParticipant', [
            'room' => $this->roomName($serverChannelId),
            'identity' => (string) $userId,
            'permission' => [
                'can_subscribe' => true,
                'can_publish' => true,
                'can_publish_data' => true,
                'can_update_metadata' => true,
                'can_publish_sources' => array_map('strtoupper', $this->sources($canSpeak)),
            ],
        ], $serverChannelId);
    }

    /** Выкинуть из комнаты (отключили от голоса / перенесли в другой канал). */
    public function removeParticipant(int $serverChannelId, int $userId): void
    {
        $this->sendRoomRequest('RemoveParticipant', [
            'room' => $this->roomName($serverChannelId),
            'identity' => (string) $userId,
        ], $serverChannelId);
    }

    /** @return list<string> */
    private function sources(bool $canSpeak): array
    {
        return $canSpeak ? self::ALL_SOURCES : array_values(array_diff(self::ALL_SOURCES, ['microphone']));
    }

    /** Ошибка LiveKit не должна ронять модерацию: клиент всё равно применит ограничение сам. */
    private function sendRoomRequest(string $method, array $body, int $serverChannelId): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $now = time();
        $adminToken = $this->sign([
            'iss' => config('services.livekit.api_key'),
            'nbf' => $now - 10,
            'exp' => $now + 60,
            'video' => ['room' => $this->roomName($serverChannelId), 'roomAdmin' => true],
        ]);

        try {
            $response = Http::timeout(self::API_TIMEOUT_SECONDS)
                ->withToken($adminToken)
                ->acceptJson()
                ->post(rtrim((string) config('services.livekit.api_host'), '/')."/twirp/livekit.RoomService/{$method}", $body);

            // not_found — человека уже нет в комнате, это нормально.
            if ($response->failed() && $response->json('code') !== 'not_found') {
                Log::warning('livekit_api_failed', ['method' => $method, 'status' => $response->status(), 'body' => mb_substr($response->body(), 0, 300)]);
            }
        } catch (Throwable $e) {
            Log::warning('livekit_api_error', ['method' => $method, 'error' => $e->getMessage()]);
        }
    }

    /** JWT HS256 — формат токенов LiveKit (https://docs.livekit.io/home/get-started/authentication/). */
    private function sign(array $claims): string
    {
        $encode = fn (array $part) => rtrim(strtr(base64_encode(json_encode($part, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), '+/', '-_'), '=');
        $unsigned = $encode(['alg' => 'HS256', 'typ' => 'JWT']).'.'.$encode($claims);
        $signature = rtrim(strtr(base64_encode(hash_hmac('sha256', $unsigned, (string) config('services.livekit.api_secret'), true)), '+/', '-_'), '=');

        return $unsigned.'.'.$signature;
    }
}
