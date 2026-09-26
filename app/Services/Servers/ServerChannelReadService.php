<?php

namespace App\Services\Servers;

use App\Enums\ServerChannelKind;
use App\Models\Conversations\Message;
use App\Models\Servers\Server;
use App\Models\Servers\ServerChannel;
use App\Models\Servers\ServerChannelRead;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Непрочитанное в текстовых каналах серверов: канал «непрочитан», если после отметки
 * пользователя в нём писал кто-то другой (свои сообщения не в счёт — как в Discord).
 */
class ServerChannelReadService
{
    public function __construct(private readonly ServerChannelService $channels) {}

    /**
     * @param  Collection<int, ServerChannel>  $channels
     * @return array<int, array{last_message_id: int, last_read_message_id: int}>
     */
    public function stateFor(User $user, Collection $channels): array
    {
        $ids = $channels
            ->filter(fn (ServerChannel $channel) => $this->isTextual($channel))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $last = Message::query()
            ->selectRaw('server_channel_id, MAX(id) as last_id')
            ->whereIn('server_channel_id', $ids)
            ->where('user_id', '!=', $user->id)
            ->where('type', '!=', 'system')
            ->groupBy('server_channel_id')
            ->pluck('last_id', 'server_channel_id');

        $read = ServerChannelRead::query()
            ->where('user_id', $user->id)
            ->whereIn('server_channel_id', $ids)
            ->pluck('last_read_message_id', 'server_channel_id');

        $state = [];
        foreach ($ids as $id) {
            $state[$id] = [
                'last_message_id' => (int) ($last[$id] ?? 0),
                'last_read_message_id' => (int) ($read[$id] ?? 0),
            ];
        }

        return $state;
    }

    public function hasUnread(User $user, Server $server): bool
    {
        foreach ($this->stateFor($user, $this->channels->listForServer($server, $user)) as $row) {
            if ($row['last_message_id'] > $row['last_read_message_id']) {
                return true;
            }
        }

        return false;
    }

    /** Отметка только растёт: старая вкладка не «разчитает» то, что прочитано в новой. */
    public function markRead(User $user, ServerChannel $channel, ?int $upToMessageId = null): int
    {
        $latest = (int) Message::query()->where('server_channel_id', $channel->id)->max('id');
        $target = $upToMessageId === null ? $latest : min($upToMessageId, $latest);

        $row = ServerChannelRead::query()->firstOrNew(['user_id' => $user->id, 'server_channel_id' => $channel->id]);
        if ((int) $row->last_read_message_id < $target || ! $row->exists) {
            $row->last_read_message_id = max((int) $row->last_read_message_id, $target);
            $row->save();
        }

        return (int) $row->last_read_message_id;
    }

    public function markServerRead(User $user, Server $server): void
    {
        foreach ($this->channels->listForServer($server, $user) as $channel) {
            if ($this->isTextual($channel)) {
                $this->markRead($user, $channel);
            }
        }
    }

    private function isTextual(ServerChannel $channel): bool
    {
        return in_array($channel->kind, [ServerChannelKind::Text, ServerChannelKind::News], true);
    }
}
