<?php

namespace App\Http\Controllers\API\Social;

use App\Http\Controllers\Controller;
use App\Http\Resources\FriendResource;
use App\Http\Resources\MutualFriendResource;
use App\Models\User;
use App\Services\Social\FriendService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FriendController extends Controller
{
    public function __construct(private readonly FriendService $friends) {}

    /** Друзья, входящие и исходящие заявки и блокировки одним списком. */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $friends = $this->friends->friendshipsFor($user)
            ->map(fn ($friendship) => new FriendResource($friendship, $user));

        return $this->successResponse('Список друзей успешно получен', ['friends' => $friends]);
    }

    public function mutualFriends(Request $request, User $user): JsonResponse
    {
        $friends = $this->friends->mutualFriends($request->user(), $user)->values();

        return $this->successResponse('Список общих друзей успешно получен', [
            'friends' => MutualFriendResource::collection($friends)->resolve($request),
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->friends->remove($request->user(), $user);

        return $this->successResponse('Запись о дружбе удалена успешно');
    }
}
