<?php

namespace App\Http\Controllers\API\Social;

use App\Http\Controllers\Controller;
use App\Http\Requests\Friends\SendFriendRequest;
use App\Models\User;
use App\Services\Social\FriendService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Заявки в друзья. Заявка определяется парой «текущий пользователь — {user}». */
class FriendRequestController extends Controller
{
    public function __construct(private readonly FriendService $friends) {}

    public function store(SendFriendRequest $request): JsonResponse
    {
        $outcome = $this->friends->sendRequest($request->user(), $request->friend());

        return $this->successResponse($outcome->message(), [], $outcome->createsRequest() ? 201 : 200);
    }

    /** Принять входящую заявку. */
    public function update(Request $request, User $user): JsonResponse
    {
        $this->friends->accept($request->user(), $user);

        return $this->successResponse('Запрос на дружбу принят успешно');
    }

    /** Отклонить входящую заявку или отменить свою. */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $resolution = $this->friends->reject($request->user(), $user);

        return $this->successResponse($resolution->message(), ['action' => $resolution->value]);
    }
}
