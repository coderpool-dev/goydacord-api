<?php

namespace App\Http\Controllers\API\Integrations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Integrations\ConnectYandexMusicTokenRequest;
use App\Models\User;
use App\Services\Integrations\YandexMusicConnectionService;
use App\Services\Integrations\YandexMusicService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class YandexMusicController extends Controller
{
    public function __construct(
        private readonly YandexMusicConnectionService $connections,
        private readonly YandexMusicService $music,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return $this->successResponse('Статус Яндекс Музыки', $this->connections->status($request->user()));
    }

    /** Привязка по коду с устройства: клиент показывает код и опрашивает poll. */
    public function startDeviceAuth(Request $request): JsonResponse
    {
        return $this->successResponse('Код привязки создан', $this->connections->startDeviceAuth($request->user()));
    }

    public function pollDeviceAuth(Request $request): JsonResponse
    {
        return $this->successResponse('Статус привязки', $this->connections->pollDeviceAuth($request->user()));
    }

    public function connectWithToken(ConnectYandexMusicTokenRequest $request): JsonResponse
    {
        try {
            $payload = $this->connections->connectWithToken($request->user(), $request->validated('token'));
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }

        return $this->successResponse('Яндекс Музыка привязана', $payload);
    }

    public function sync(Request $request): JsonResponse
    {
        $user = $request->user()->fresh(['yandexMusicConnection']);

        return $this->successResponse('Синхронизация выполнена', [
            'track' => $this->music->syncNowPlaying($user),
            'connected' => $user->yandexMusicConnection !== null,
        ]);
    }

    public function disconnect(Request $request): JsonResponse
    {
        $this->connections->disconnect($request->user());

        return $this->successResponse('Яндекс Музыка отвязана', ['connected' => false]);
    }

    public function history(Request $request, User $user): JsonResponse
    {
        $visible = $request->user()->can('viewActivity', $user);

        return $this->successResponse('История Яндекс Музыки', [
            'visible' => $visible,
            'tracks' => $visible ? $this->music->history($user, $request->integer('limit', 12)) : [],
        ]);
    }
}
