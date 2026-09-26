<?php

namespace App\Services\Account;

use App\Models\Conversations\Call;
use App\Models\Conversations\ChannelMember;
use App\Models\Conversations\Message;
use App\Models\Servers\Server;
use App\Models\Servers\ServerBan;
use App\Models\Servers\ServerInvite;
use App\Models\Servers\ServerMember;
use App\Models\Social\Friend;
use App\Models\User;
use App\Services\Servers\ServerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UserDeletionService
{
    public function __construct(private readonly ServerService $servers) {}

    public function deleteByLogin(string $login): bool
    {
        $user = User::query()->where('login', trim($login))->first();
        if (! $user) {
            return false;
        }

        $this->deleteUser($user);

        return true;
    }

    public function deleteUser(User $user): void
    {
        DB::transaction(function () use ($user) {
            $userId = (int) $user->id;

            if ($user->avatar && $user->avatar !== 'default.png') {
                Storage::disk('public')->delete('avatars/'.$user->avatar);
            }
            if ($user->banner) {
                Storage::disk('public')->delete('banners/'.$user->banner);
            }

            $user->tokens()->delete();

            $initiatedCallIds = Call::query()
                ->where('initiator_id', $userId)
                ->pluck('call_id');

            DB::table('call_sessions')->where('user_id', $userId)->delete();

            foreach ($initiatedCallIds as $callId) {
                DB::table('call_sessions')->where('call_id', $callId)->delete();
                Call::query()->where('call_id', $callId)->delete();
            }

            $this->deleteServerFootprint($userId);

            ChannelMember::query()->where('users_id', $userId)->delete();
            Friend::query()
                ->where('users_id', $userId)
                ->orWhere('friend_id', $userId)
                ->delete();
            Message::query()->where('user_id', $userId)->delete();
            DB::table('site_presence_sessions')->where('user_id', $userId)->delete();
            DB::table('push_subscriptions')->where('user_id', $userId)->delete();

            $user->delete();
        });
    }

    /**
     * Серверные таблицы ссылаются на users без ON DELETE CASCADE, и без этой чистки удаление
     * падает на внешнем ключе. Свои серверы удаляются целиком; баны, выданные пользователем
     * на чужих серверах, переходят к владельцу сервера, а не снимаются.
     */
    private function deleteServerFootprint(int $userId): void
    {
        Server::query()->where('owner_id', $userId)->pluck('id')
            ->each(fn ($serverId) => $this->servers->delete((int) $serverId));

        $memberIds = ServerMember::query()->where('user_id', $userId)->pluck('id');
        DB::table('server_member_role')->whereIn('server_member_id', $memberIds)->delete();
        DB::table('server_channel_member_overwrites')->whereIn('server_member_id', $memberIds)->delete();
        ServerMember::query()->whereKey($memberIds)->delete();

        ServerBan::query()->where('user_id', $userId)->delete();
        DB::table('server_bans')->where('banned_by', $userId)->update([
            'banned_by' => DB::raw('(select owner_id from servers where servers.id = server_bans.server_id)'),
        ]);
        ServerInvite::query()->where('created_by', $userId)->delete();
    }
}
