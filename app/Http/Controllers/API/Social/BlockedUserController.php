<?php

namespace App\Http\Controllers\API\Social;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Social\FriendService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlockedUserController extends Controller
{
    public function __construct(private readonly FriendService $friends) {}

    /** PUT идемпотентен: повторная блокировка ничего не меняет. */
    public function update(Request $request, User $user): JsonResponse
    {
        $this->friends->block($request->user(), $user);

        return $this->successResponse('Пользователь заблокирован успешно');
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->friends->unblock($request->user(), $user);

        return $this->successResponse('Пользователь разблокирован успешно');
    }
}
