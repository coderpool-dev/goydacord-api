<?php

namespace App\Http\Controllers\API\Conversations;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** STUN/TURN-серверы для WebRTC. Отдаём только свой coturn, сторонние не используем. */
class IceServersController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $host = config('services.webrtc.turn_host');

        if (empty($host)) {
            return $this->successResponse('TURN не настроен', ['ice_servers' => []]);
        }

        $mode = $request->query('mode');
        $includeTcpFallback = $mode === 'fallback' || $request->boolean('include_tcp');

        $turnUrls = ["turn:{$host}:3478?transport=udp"];

        $tlsHost = config('services.webrtc.turn_tls_host');
        $tlsPort = (int) config('services.webrtc.turn_tls_port');

        if ($includeTcpFallback) {
            $turnUrls[] = "turn:{$host}:3478?transport=tcp";

            if (! empty($tlsHost) && $tlsPort > 0) {
                $turnUrls[] = "turns:{$tlsHost}:{$tlsPort}?transport=tcp";
            }
        }

        return $this->successResponse('Серверы для WebRTC', [
            'ice_servers' => [
                ['urls' => "stun:{$host}:3478"],
                ['urls' => $turnUrls, ...$this->turnCredentials((int) $request->user()->id)],
            ],
        ]);
    }

    /**
     * Временные логин и пароль по схеме TURN REST API: логин «срок_годности:id», пароль —
     * HMAC-SHA1 логина на общем секрете coturn. Утёкшая пара перестаёт работать через TTL,
     * и по логину в логах coturn видно, чей это трафик. Без секрета — статическая пара (локальная разработка).
     *
     * @return array{username: string|null, credential: string|null}
     */
    private function turnCredentials(int $userId): array
    {
        $secret = (string) config('services.webrtc.turn_secret');

        if ($secret === '') {
            return [
                'username' => config('services.webrtc.turn_username'),
                'credential' => config('services.webrtc.turn_credential'),
            ];
        }

        $username = (now()->timestamp + (int) config('services.webrtc.turn_credential_ttl')).':'.$userId;

        return [
            'username' => $username,
            'credential' => base64_encode(hash_hmac('sha1', $username, $secret, true)),
        ];
    }
}
