<?php

namespace App\Http\Controllers\API\Conversations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Channels\ChannelRecipientsRequest;
use App\Http\Requests\Channels\SetMemberRoleRequest;
use App\Http\Resources\ChannelMemberResource;
use App\Http\Resources\ChannelSummaryResource;
use App\Models\Conversations\Channel;
use App\Models\User;
use App\Services\Conversations\ChannelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChannelMemberController extends Controller
{
    public function __construct(private readonly ChannelService $channels) {}

    public function index(Request $request, Channel $channel): JsonResponse
    {
        $this->authorize('view', $channel);

        $members = $this->channels->activeMembers($channel);

        return $this->successResponse('Список участников успешно получен', [
            'channel' => [
                ...(new ChannelSummaryResource($channel))->resolve($request),
                'members_count' => $members->count(),
                'owner_id' => $this->channels->resolveOwnerId((int) $channel->id),
                'my_role' => $this->channels->roleOf($request->user(), $channel),
            ],
            'members' => $members->map(fn ($member) => new ChannelMemberResource($member)),
        ]);
    }

    public function store(ChannelRecipientsRequest $request, Channel $channel): JsonResponse
    {
        $this->authorize('addMembers', $channel);

        $this->channels->addMembers($request->user(), $channel, $request->recipientIds());

        return $this->successResponse('Участники добавлены', [], 201);
    }

    public function update(SetMemberRoleRequest $request, Channel $channel, User $member): JsonResponse
    {
        $this->authorize('setRole', $channel);

        $this->channels->setMemberRole(
            $request->user(),
            (int) $channel->id,
            (int) $member->id,
            $request->validated('role'),
        );

        return $this->successResponse('Роль обновлена');
    }

    public function destroy(Request $request, Channel $channel, User $member): JsonResponse
    {
        $this->authorize('kick', $channel);

        $this->channels->kickMember($request->user(), $channel, $member);

        return $this->successResponse('Участник исключён');
    }
}
