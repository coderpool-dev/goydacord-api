<?php

namespace App\Http\Controllers\API\Presence;

use App\Http\Controllers\Controller;
use App\Http\Requests\Activity\UpdateGameSessionRequest;
use App\Models\User;
use App\Services\Presence\ActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ActivityController extends Controller
{
    public function __construct(private readonly ActivityService $activity) {}

    public function show(Request $request, User $user): JsonResponse
    {
        // Профиль открывают все, поэтому вместо 403 отдаём пустую сводку, и клиент пишет, что активность скрыта.
        if ($request->user()->cannot('viewActivity', $user)) {
            return $this->successResponse('Активность скрыта', [
                'visible' => false,
                'featured_streak' => null,
                'game_streaks' => [],
                'history' => [],
            ]);
        }

        return $this->successResponse('Активность пользователя', [
            'visible' => true,
            ...$this->activity->profileSummary($user),
        ]);
    }

    /** Десктоп-клиент сообщает о запуске и закрытии игры. */
    public function update(UpdateGameSessionRequest $request): JsonResponse
    {
        return $request->isStart()
            ? $this->startGame($request->user(), (string) $request->game())
            : $this->endGame($request->user(), $request->game());
    }

    public function pingGame(Request $request): JsonResponse
    {
        $this->activity->confirmGameStatus($request->user());

        return $this->successResponse('Статус игры подтверждён');
    }

    private function startGame(User $user, string $game): JsonResponse
    {
        try {
            $session = $this->activity->recordGameStart($user, $game);
        } catch (InvalidArgumentException) {
            // Вместо игры пришёл лаунчер или заголовок окна. Для старых клиентов это
            // обычная ситуация, поэтому отвечаем успехом и ничего не сохраняем.
            return $this->successResponse('Проигнорировано', ['session' => null]);
        }

        return $this->successResponse('Игровая сессия начата', [
            'session' => [
                'id' => $session->id,
                'game' => $session->game->name ?? $game,
                'started_at' => $session->started_at->toIso8601String(),
            ],
            'game_streak' => $this->activity->calculateGameStreak($user, $game),
        ], 201);
    }

    private function endGame(User $user, ?string $game): JsonResponse
    {
        $session = $this->activity->recordGameEnd($user, $game);

        return $this->successResponse('Игровая сессия завершена', [
            'session' => $session ? [
                'id' => $session->id,
                'game' => $session->game->name ?? $game,
                'started_at' => $session->started_at->toIso8601String(),
                'ended_at' => $session->ended_at?->toIso8601String(),
                'duration_seconds' => $session->durationSeconds(),
            ] : null,
        ]);
    }
}
