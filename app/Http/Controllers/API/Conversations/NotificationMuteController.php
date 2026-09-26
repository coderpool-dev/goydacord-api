<?php

namespace App\Http\Controllers\API\Conversations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notifications\MuteNotificationsRequest;
use App\Http\Requests\Notifications\UnmuteNotificationsRequest;
use App\Http\Resources\NotificationMuteResource;
use App\Services\Conversations\NotificationMuteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** «Заглушить» чат / канал сервера / сервер на время или пока не включат. */
class NotificationMuteController extends Controller
{
    public function __construct(private readonly NotificationMuteService $mutes) {}

    public function index(Request $request): JsonResponse
    {
        return $this->successResponse('Отключённые уведомления', ['mutes' => $this->activeMutes((int) $request->user()->id)]);
    }

    public function store(MuteNotificationsRequest $request): JsonResponse
    {
        $this->authorize('view', $request->target());

        $userId = (int) $request->user()->id;
        $this->mutes->mute($userId, $request->validated('target_type'), $request->integer('target_id'), $request->minutes());

        return $this->successResponse('Уведомления отключены', ['mutes' => $this->activeMutes($userId)]);
    }

    public function destroy(UnmuteNotificationsRequest $request): JsonResponse
    {
        $userId = (int) $request->user()->id;
        $this->mutes->unmute($userId, $request->validated('target_type'), $request->integer('target_id'));

        return $this->successResponse('Уведомления включены', ['mutes' => $this->activeMutes($userId)]);
    }

    private function activeMutes(int $userId): array
    {
        return NotificationMuteResource::collection($this->mutes->activeFor($userId))->resolve();
    }
}
